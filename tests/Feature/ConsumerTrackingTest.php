<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Contracts\ConsumerResolverInterface;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Tracking\ConsumerHasher;
use Grazulex\ApiRoute\Tracking\DatabaseTracker;
use Grazulex\ApiRoute\Tracking\NullTracker;
use Grazulex\ApiRoute\Tracking\RedisTracker;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

final class ConsumerTrackingTestResolver implements ConsumerResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return 'tenant:acme';
    }
}

beforeEach(function (): void {
    config([
        'database.default' => 'testing',
        'database.connections.testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
        'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
        'apiroute.tracking.enabled' => true,
        'apiroute.tracking.driver' => 'database',
        'apiroute.tracking.consumers.enabled' => true,
    ]);

    (require __DIR__ . '/../../database/migrations/create_api_version_stats_table.php')->up();
    (require __DIR__ . '/../../database/migrations/create_api_version_consumers_table.php')->up();

    $this->app->instance(VersionTrackerInterface::class, new DatabaseTracker);
});

afterEach(fn () => Carbon::setTestNow());

function registerConsumerTrackingVersion(bool $deprecated = false): void
{
    $definition = ApiRoute::version('v1', function (): void {
        Route::get('test', fn () => response()->json(['ok' => true]));
        Route::get('fail', fn () => response()->json(['error' => true], 500));
    });

    if ($deprecated) {
        $definition->deprecated('2025-06-01');
    }
}

test('authenticated user is tracked with a hashed identifier only', function (): void {
    registerConsumerTrackingVersion();

    $this->actingAs(new GenericUser(['id' => 42]))
        ->get('/api/v1/test')
        ->assertOk();

    $rows = DB::table('api_version_consumers')->get();
    $expectedHash = app(ConsumerHasher::class)->hash('user:42');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->version)->toBe('v1')
        ->and($rows[0]->consumer_hash)->toBe($expectedHash)
        ->and($rows[0]->consumer_hash)->not->toContain('42')
        ->and((int) $rows[0]->requests_count)->toBe(1)
        ->and((int) $rows[0]->success_count)->toBe(1)
        ->and($rows[0]->last_seen_at)->not->toBeNull()
        ->and(DB::table('api_version_stats')->count())->toBe(1);
});

test('client header identifies anonymous consumers when configured', function (): void {
    config(['apiroute.tracking.consumers.header' => 'X-Client-Id']);
    registerConsumerTrackingVersion();

    $this->get('/api/v1/fail', ['X-Client-Id' => 'mobile-app'])->assertStatus(500);

    $row = DB::table('api_version_consumers')->first();

    expect($row)->not->toBeNull()
        ->and($row->consumer_hash)->toBe(app(ConsumerHasher::class)->hash('client:mobile-app'))
        ->and((int) $row->error_count)->toBe(1)
        ->and((int) $row->success_count)->toBe(0);
});

test('client header is ignored when not configured', function (): void {
    registerConsumerTrackingVersion();

    $this->get('/api/v1/test', ['X-Client-Id' => 'mobile-app'])->assertOk();

    expect(DB::table('api_version_consumers')->count())->toBe(0)
        ->and(DB::table('api_version_stats')->count())->toBe(1);
});

test('nothing is tracked per consumer when consumer tracking is disabled', function (): void {
    config(['apiroute.tracking.consumers.enabled' => false]);
    registerConsumerTrackingVersion();

    $this->actingAs(new GenericUser(['id' => 42]))
        ->get('/api/v1/test')
        ->assertOk();

    expect(DB::table('api_version_consumers')->count())->toBe(0)
        ->and(DB::table('api_version_stats')->count())->toBe(1);
});

test('only_deprecated skips consumers of active versions', function (): void {
    config(['apiroute.tracking.consumers.only_deprecated' => true]);
    registerConsumerTrackingVersion();

    $this->actingAs(new GenericUser(['id' => 42]))
        ->get('/api/v1/test')
        ->assertOk();

    expect(DB::table('api_version_consumers')->count())->toBe(0);
});

test('only_deprecated tracks consumers of deprecated versions', function (): void {
    config(['apiroute.tracking.consumers.only_deprecated' => true]);
    registerConsumerTrackingVersion(deprecated: true);

    $this->actingAs(new GenericUser(['id' => 42]))
        ->get('/api/v1/test')
        ->assertOk();

    expect(DB::table('api_version_consumers')->count())->toBe(1);
});

test('custom resolver can be configured', function (): void {
    config(['apiroute.tracking.consumers.resolver' => ConsumerTrackingTestResolver::class]);
    registerConsumerTrackingVersion();

    $this->get('/api/v1/test')->assertOk();

    expect(DB::table('api_version_consumers')->value('consumer_hash'))
        ->toBe(app(ConsumerHasher::class)->hash('tenant:acme'));
});

test('consumers are not tracked when no hash key is available', function (): void {
    config(['app.key' => null, 'apiroute.tracking.consumers.hash_key' => null]);
    registerConsumerTrackingVersion();

    $this->actingAs(new GenericUser(['id' => 42]))
        ->get('/api/v1/test')
        ->assertOk();

    expect(DB::table('api_version_consumers')->count())->toBe(0)
        ->and(DB::table('api_version_stats')->count())->toBe(1);
});

test('hasher is a deterministic keyed HMAC and honours hash_key', function (): void {
    $hasher = new ConsumerHasher;
    $default = $hasher->hash('user:42');

    expect($default)->toBe(hash_hmac('sha256', 'user:42', (string) config('app.key')))
        ->and($hasher->hash('user:42'))->toBe($default)
        ->and(strlen($default))->toBe(64);

    config(['apiroute.tracking.consumers.hash_key' => 'dedicated-secret']);

    expect($hasher->hash('user:42'))->toBe(hash_hmac('sha256', 'user:42', 'dedicated-secret'))
        ->and($hasher->hash('user:42'))->not->toBe($default);
});

test('hasher refuses to hash without a key', function (): void {
    config(['app.key' => '', 'apiroute.tracking.consumers.hash_key' => null]);

    $hasher = new ConsumerHasher;

    expect($hasher->canHash())->toBeFalse()
        ->and(fn () => $hasher->hash('user:42'))->toThrow(RuntimeException::class);
});

test('database tracker aggregates consumers per day and reports them by activity', function (): void {
    $tracker = new DatabaseTracker;

    Carbon::setTestNow('2026-11-01 10:00:00');
    $tracker->trackConsumer('v1', 'old-consumer', 200);

    Carbon::setTestNow('2026-11-17 10:00:00');
    $tracker->trackConsumer('v1', 'busy', 200);
    $tracker->trackConsumer('v1', 'quiet', 200);
    $tracker->trackConsumer('v2', 'busy', 200);

    Carbon::setTestNow('2026-11-18 09:30:00');
    $tracker->trackConsumer('v1', 'busy', 200);
    $tracker->trackConsumer('v1', 'busy', 404);

    expect(DB::table('api_version_consumers')->where('consumer_hash', 'busy')->where('version', 'v1')->count())->toBe(2);

    $stats = $tracker->getConsumerStats('v1', 7);

    expect($stats)->toHaveCount(2)
        ->and($stats[0]['consumer'])->toBe('busy')
        ->and($stats[0]['total_requests'])->toBe(3)
        ->and($stats[0]['success_requests'])->toBe(2)
        ->and($stats[0]['error_requests'])->toBe(1)
        ->and($stats[0]['last_seen_at'])->toBe(Carbon::parse('2026-11-18 09:30:00')->toIso8601String())
        ->and($stats[1]['consumer'])->toBe('quiet')
        ->and($stats[1]['total_requests'])->toBe(1)
        ->and($tracker->getConsumerStats('v1', 30))->toHaveCount(3)
        ->and($tracker->getConsumerStats('v3', 30))->toBe([]);
});

test('api:stats --consumers lists the consumers of a version', function (): void {
    $tracker = new DatabaseTracker;
    $tracker->trackConsumer('v1', str_repeat('a', 64), 200);
    $tracker->trackConsumer('v1', str_repeat('a', 64), 500);

    $this->artisan('api:stats', ['--consumers' => true, '--api-version' => 'v1'])
        ->expectsOutputToContain('Consumers of version v1')
        ->expectsOutputToContain(str_repeat('a', 64))
        ->assertSuccessful();
});

test('api:stats --consumers outputs JSON', function (): void {
    (new DatabaseTracker)->trackConsumer('v1', 'abc', 200);

    $this->artisan('api:stats', ['--consumers' => true, '--api-version' => 'v1', '--json' => true])
        ->expectsOutputToContain('"consumer": "abc"')
        ->assertSuccessful();
});

test('api:stats --consumers requires a version', function (): void {
    $this->artisan('api:stats', ['--consumers' => true])
        ->expectsOutputToContain('requires --api-version')
        ->assertFailed();
});

test('api:stats --consumers warns when there is no data', function (): void {
    $this->app->instance(VersionTrackerInterface::class, new NullTracker);

    $this->artisan('api:stats', ['--consumers' => true, '--api-version' => 'v1'])
        ->expectsOutputToContain("No consumer statistics available for version 'v1'")
        ->assertSuccessful();
});

test('api:stats --consumers warns when the driver does not support it', function (): void {
    $this->app->instance(VersionTrackerInterface::class, new RedisTracker);

    $this->artisan('api:stats', ['--consumers' => true, '--api-version' => 'v1'])
        ->expectsOutputToContain('does not support per-consumer statistics')
        ->assertSuccessful();
});
