<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => EndpointLifecycleResolver::flush());

function defineDiffVersions(): void
{
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok')->name('users.index');
        Route::get('users/{id}', fn (string $id) => 'ok');
        Route::delete('users/{id}', fn (string $id) => 'ok');
        Route::get('legacy', fn () => 'ok');
    });

    ApiRoute::version('v2', function (): void {
        Route::get('users', fn () => 'ok')->name('users.index');
        Route::get('users/{user}', fn (string $user) => 'ok');
        Route::delete('users/{user}', fn (string $user) => 'ok')
            ->deprecated(since: '2026-01-01', sunset: '2027-01-01', successor: '/api/v3/users');
        Route::post('users', fn () => 'ok')->name('users.store');
    });
}

/**
 * @param  array<string, mixed>  $arguments
 *
 * @return array<string, mixed>
 */
function diffJson(array $arguments): array
{
    Artisan::call('api:diff', [...$arguments, '--json' => true]);

    return json_decode(Artisan::output(), true);
}

test('json output lists added, removed and deprecated routes', function (): void {
    defineDiffVersions();

    $json = diffJson(['from' => 'v1', 'to' => 'v2']);

    expect($json['from'])->toBe('v1')
        ->and($json['to'])->toBe('v2')
        ->and($json['added'])->toBe([
            ['method' => 'POST', 'uri' => 'users', 'name' => 'users.store'],
        ])
        ->and($json['removed'])->toBe([
            ['method' => 'GET', 'uri' => 'legacy', 'name' => null],
        ])
        ->and($json['deprecated'])->toBe([[
            'method' => 'DELETE',
            'uri' => 'users/{user}',
            'name' => null,
            'since' => '2026-01-01',
            'sunset' => '2027-01-01',
            'successor' => '/api/v3/users',
        ]])
        ->and($json['summary'])->toBe([
            'added' => 1,
            'removed' => 1,
            'deprecated' => 1,
            'unchanged' => 2,
        ]);
});

test('reversing the versions swaps added and removed routes', function (): void {
    defineDiffVersions();

    $json = diffJson(['from' => 'v2', 'to' => 'v1']);

    expect(array_column($json['added'], 'uri'))->toBe(['legacy'])
        ->and(array_column($json['removed'], 'uri'))->toBe(['users'])
        ->and(array_column($json['removed'], 'method'))->toBe(['POST'])
        ->and($json['deprecated'])->toBe([]);
});

test('routes deprecated through controller attributes are detected', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things', fn () => 'ok');
    });
    ApiRoute::version('v2', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'index']);
    });

    $json = diffJson(['from' => 'v1', 'to' => 'v2']);

    expect($json['deprecated'])->toHaveCount(1)
        ->and($json['deprecated'][0]['uri'])->toBe('things')
        ->and($json['deprecated'][0]['since'])->toBe('2026-01-01')
        ->and($json['deprecated'][0]['successor'])->toBe('/api/v2/legacy');
});

test('a route already deprecated in the base version is not reported again', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things', fn () => 'ok')->deprecated(since: '2026-01-01');
    });
    ApiRoute::version('v2', function (): void {
        Route::get('things', fn () => 'ok')->deprecated(since: '2026-01-01');
    });

    $json = diffJson(['from' => 'v1', 'to' => 'v2']);

    expect($json['deprecated'])->toBe([])
        ->and($json['summary']['unchanged'])->toBe(1);
});

test('text output lists the differences', function (): void {
    defineDiffVersions();

    $this->artisan('api:diff', ['from' => 'v1', 'to' => 'v2'])
        ->expectsOutputToContain('Added routes')
        ->expectsOutputToContain('Removed routes')
        ->expectsOutputToContain('Deprecated routes')
        ->expectsOutputToContain('Summary: 1 added, 1 removed, 1 deprecated, 2 unchanged')
        ->assertSuccessful();
});

test('text output reports identical versions', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });
    ApiRoute::version('v2', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $this->artisan('api:diff', ['from' => 'v1', 'to' => 'v2'])
        ->expectsOutputToContain('No differences between v1 and v2.')
        ->assertSuccessful();
});

test('fails on an unknown version', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $this->artisan('api:diff', ['from' => 'v1', 'to' => 'v9'])
        ->expectsOutputToContain("Version 'v9' not found.")
        ->assertFailed();
});

test('--fail-on-removed fails only when routes were removed', function (): void {
    defineDiffVersions();

    $this->artisan('api:diff', ['from' => 'v1', 'to' => 'v2', '--fail-on-removed' => true])
        ->assertFailed();

    ApiRoute::version('v3', function (): void {
        Route::get('users', fn () => 'ok');
        Route::get('users/{id}', fn (string $id) => 'ok');
        Route::delete('users/{id}', fn (string $id) => 'ok');
        Route::post('users', fn () => 'ok');
    });

    $this->artisan('api:diff', ['from' => 'v2', 'to' => 'v3', '--fail-on-removed' => true])
        ->assertSuccessful();
});

test('--fail-on-changes fails on any difference', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });
    ApiRoute::version('v2', function (): void {
        Route::get('users', fn () => 'ok');
        Route::post('users', fn () => 'ok');
    });
    ApiRoute::version('v3', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $this->artisan('api:diff', ['from' => 'v1', 'to' => 'v2', '--fail-on-changes' => true])
        ->assertFailed();

    $this->artisan('api:diff', ['from' => 'v1', 'to' => 'v3', '--fail-on-changes' => true])
        ->assertSuccessful();
});

test('works with the header strategy where versions share the same URIs', function (): void {
    config()->set('apiroute.strategy', 'header');

    defineDiffVersions();

    $json = diffJson(['from' => 'v1', 'to' => 'v2']);

    expect($json['summary'])->toBe([
        'added' => 1,
        'removed' => 1,
        'deprecated' => 1,
        'unchanged' => 2,
    ]);
});

test('does not alter the application routes', function (): void {
    defineDiffVersions();

    $router = app('router');
    $before = count(Route::getRoutes()->getRoutes());

    Artisan::call('api:diff', ['from' => 'v1', 'to' => 'v2']);

    expect(app('router'))->toBe($router)
        ->and(Route::getFacadeRoot())->toBeInstanceOf(Router::class)
        ->and(Route::getFacadeRoot())->toBe($router)
        ->and(count(Route::getRoutes()->getRoutes()))->toBe($before);
});
