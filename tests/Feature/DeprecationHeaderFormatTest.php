<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Http\Headers\DeprecationHeaderValue;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedActionController;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => EndpointLifecycleResolver::flush());

test('version Deprecation header defaults to an RFC 7231 HTTP-date', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->deprecated('2025-06-01');

    $this->get('/api/v1/test')
        ->assertOk()
        ->assertHeader('Deprecation', Carbon::parse('2025-06-01')->format(Carbon::RFC7231));
});

test('version Deprecation header uses the RFC 9745 format when configured', function (): void {
    config(['apiroute.headers.deprecation_format' => 'rfc9745']);

    ApiRoute::version('v1', function (): void {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->deprecated('2025-06-01')->sunset('2099-12-01');

    $this->get('/api/v1/test')
        ->assertOk()
        ->assertHeader('Deprecation', '@' . Carbon::parse('2025-06-01')->getTimestamp())
        ->assertHeader('Sunset', Carbon::parse('2099-12-01')->format(Carbon::RFC7231));
});

test('endpoint Deprecation header uses the RFC 9745 format when configured', function (): void {
    config(['apiroute.headers.deprecation_format' => 'rfc9745']);

    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    });

    $this->get('/api/v1/items')
        ->assertOk()
        ->assertHeader('Deprecation', '@' . Carbon::parse('2026-03-01')->getTimestamp())
        ->assertHeader('Sunset', Carbon::parse('2099-01-01')->format(Carbon::RFC7231));
});

test('endpoint Deprecation header keeps the RFC 7231 format explicitly configured', function (): void {
    config(['apiroute.headers.deprecation_format' => 'rfc7231']);

    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    });

    $this->get('/api/v1/items')
        ->assertOk()
        ->assertHeader('Deprecation', Carbon::parse('2026-03-01')->format(Carbon::RFC7231));
});

test('RFC 9745 value is a unix timestamp independent of the timezone', function (): void {
    config(['apiroute.headers.deprecation_format' => 'rfc9745']);

    expect(DeprecationHeaderValue::format(Carbon::parse('2025-06-01 00:00:00', 'UTC')))->toBe('@1748736000')
        ->and(DeprecationHeaderValue::format(Carbon::parse('2025-06-01 02:00:00', 'Europe/Brussels')))->toBe('@1748736000');
});

test('format option is case-insensitive', function (): void {
    config(['apiroute.headers.deprecation_format' => 'RFC9745']);

    expect(DeprecationHeaderValue::format(Carbon::parse('2025-06-01 00:00:00', 'UTC')))->toBe('@1748736000');
});

test('missing or unknown format falls back to the RFC 7231 HTTP-date', function (mixed $format): void {
    config(['apiroute.headers.deprecation_format' => $format]);

    $date = Carbon::parse('2025-06-01 00:00:00', 'UTC');

    expect(DeprecationHeaderValue::format($date))->toBe($date->format(Carbon::RFC7231));
})->with([
    'null' => [null],
    'unknown' => ['iso8601'],
    'non-string' => [42],
]);
