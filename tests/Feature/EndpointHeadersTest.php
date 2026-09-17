<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedActionController;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => EndpointLifecycleResolver::flush());

test('deprecated endpoint in an active version gets deprecation headers', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    });

    $response = $this->get('/api/v1/items');

    $response->assertOk()
        ->assertHeader('X-API-Version', 'v1')
        ->assertHeader('X-API-Version-Status', 'active')
        ->assertHeader('X-API-Endpoint-Status', 'deprecated')
        ->assertHeader('Deprecation', Carbon::parse('2026-03-01')->format(Carbon::RFC7231))
        ->assertHeader('Sunset', Carbon::parse('2099-01-01')->format(Carbon::RFC7231))
        ->assertHeader('Link', '<https://api.example.com/v2/items>; rel="successor-version"');
});

test('docs adds a Link rel="deprecation" combined with the successor link', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('legacy', [DeprecatedClassController::class, 'index']);
    });

    $this->get('/api/v1/legacy')
        ->assertOk()
        ->assertHeader('Link', '<http://localhost/api/v2/legacy>; rel="successor-version", <https://docs.example.com/legacy>; rel="deprecation"');
});

test('fresh endpoint has no endpoint headers', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('fresh', [DeprecatedActionController::class, 'fresh']);
    });

    $response = $this->get('/api/v1/fresh');

    $response->assertOk()->assertHeaderMissing('X-API-Endpoint-Status')->assertHeaderMissing('Deprecation');
});

test('endpoint values override version values, version status stays', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    })->deprecated('2025-01-01')->sunset('2030-01-01')->setSuccessor('v2');

    $this->get('/api/v1/items')
        ->assertOk()
        ->assertHeader('X-API-Version-Status', 'deprecated')
        ->assertHeader('Deprecation', Carbon::parse('2026-03-01')->format(Carbon::RFC7231))
        ->assertHeader('Sunset', Carbon::parse('2099-01-01')->format(Carbon::RFC7231))
        ->assertHeader('Link', '<https://api.example.com/v2/items>; rel="successor-version"');
});

test('closure route with the deprecated macro gets the same headers', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('closure', fn () => response()->json(['ok' => true]))->deprecated(since: '2026-05-05', successor: '/api/v2/closure');
    });

    $this->get('/api/v1/closure')
        ->assertOk()
        ->assertHeader('X-API-Endpoint-Status', 'deprecated')
        ->assertHeader('Deprecation', Carbon::parse('2026-05-05')->format(Carbon::RFC7231))
        ->assertHeader('Link', '<http://localhost/api/v2/closure>; rel="successor-version"');
});

test('endpoint_status header can be disabled by config', function (): void {
    config(['apiroute.headers.include.endpoint_status' => false]);
    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    });

    $this->get('/api/v1/items')->assertOk()->assertHeaderMissing('X-API-Endpoint-Status')->assertHeader('Deprecation', Carbon::parse('2026-03-01')->format(Carbon::RFC7231));
});

test('headers.enabled=false disables endpoint headers too', function (): void {
    config(['apiroute.headers.enabled' => false]);
    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    });

    $this->get('/api/v1/items')->assertOk()->assertHeaderMissing('Deprecation')->assertHeaderMissing('X-API-Endpoint-Status');
});

test('parameterised successor route is generated with the current route parameters', function (): void {
    Route::get('/api/v2/things/{id}', fn (string $id) => 'ok')->name('api.v2.things.show');
    ApiRoute::version('v1', function (): void {
        Route::get('things/{id}', fn (string $id) => response()->json(['id' => $id]))->deprecated(successor: 'api.v2.things.show');
    });

    $this->get('/api/v1/things/5')
        ->assertOk()
        ->assertJson(['id' => '5'])
        ->assertHeader('Link', '<http://localhost/api/v2/things/5>; rel="successor-version"');
});

test('successor route missing a required parameter is logged and skipped', function (): void {
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'successor route cannot be generated'));
    Route::get('/api/v2/things/{id}', fn (string $id) => 'ok')->name('api.v2.things.show');
    ApiRoute::version('v1', function (): void {
        Route::get('things', fn () => response()->json(['ok' => true]))->deprecated(successor: 'api.v2.things.show');
    });

    $this->get('/api/v1/things')
        ->assertOk()
        ->assertJson(['ok' => true])
        ->assertHeaderMissing('Link')
        ->assertHeader('X-API-Endpoint-Status', 'deprecated');
});
