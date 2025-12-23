<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Support\Facades\Route;

test('rejects sunset version with 410 gone', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->sunset(Carbon::now()->subDay());

    $response = $this->get('/api/v1/test');

    $response->assertStatus(410);
    $response->assertJson(['error' => 'api_version_sunset']);
});

test('sunset response includes version info', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->sunset(Carbon::now()->subDay())->setSuccessor('v2');

    $response = $this->get('/api/v1/test');

    $response->assertStatus(410);
    $response->assertJsonStructure([
        'error',
        'message',
        'sunset_date',
        'successor',
    ]);
    $response->assertJson(['successor' => 'v2']);
});

test('allows sunset version when action is allow', function () {
    config(['apiroute.sunset.action' => 'allow']);

    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->sunset(Carbon::now()->subDay());

    $response = $this->get('/api/v1/test');

    $response->assertOk();
});

test('warns for sunset version when action is warn', function () {
    config(['apiroute.sunset.action' => 'warn']);

    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->sunset(Carbon::now()->subDay());

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('X-API-Version-Status', 'sunset');
});

test('does not reject future sunset date', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->deprecated('2025-06-01')->sunset('2099-12-01');

    $response = $this->get('/api/v1/test');

    $response->assertOk();
});
