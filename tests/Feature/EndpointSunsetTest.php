<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    EndpointLifecycleResolver::flush();
    Route::get('/api/v2/things', fn () => 'ok')->name('api.v2.things.index');
});

test('sunset endpoint is rejected with 410 and the json body', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $response = $this->get('/api/v1/things');

    $response->assertStatus(410)
        ->assertExactJson([
            'error' => 'endpoint_sunset',
            'message' => 'Use things v2',
            'sunset_at' => '2020-01-01T00:00:00+00:00',
            'successor' => 'http://localhost/api/v2/things',
            'docs' => 'https://docs.example.com/legacy',
        ])
        ->assertHeader('X-API-Endpoint-Status', 'sunset')
        ->assertHeader('X-API-Version', 'v1');
});

test('sunset status code follows config', function (): void {
    config(['apiroute.sunset.status_code' => 404]);
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $this->get('/api/v1/things')->assertStatus(404)->assertJson(['error' => 'endpoint_sunset']);
});

test('warn lets the request through with sunset headers', function (): void {
    config(['apiroute.sunset.action' => 'warn']);
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $this->get('/api/v1/things')->assertOk()->assertJson(['ok' => true])->assertHeader('X-API-Endpoint-Status', 'sunset')->assertHeader('Sunset');
});

test('allow lets the request through and still reports headers', function (): void {
    config(['apiroute.sunset.action' => 'allow']);
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $this->get('/api/v1/things')->assertOk()->assertHeader('X-API-Endpoint-Status', 'sunset');
});

test('a deprecated but not yet sunset endpoint is served', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('legacy', [DeprecatedClassController::class, 'index']);
    });

    $this->get('/api/v1/legacy')->assertOk()->assertJson(['ok' => true]);
});

test('default message is used when no reason is given', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('gone', fn () => 'never')->deprecated(sunset: '2020-01-01');
    });

    $this->get('/api/v1/gone')->assertStatus(410)->assertJson(['message' => 'This endpoint is no longer available.', 'successor' => null, 'docs' => null]);
});
