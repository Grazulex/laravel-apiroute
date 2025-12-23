<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Support\Facades\Route;

test('resolves version from uri path', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['version' => 'v1']));
    });

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertJson(['version' => 'v1']);
    $response->assertHeader('X-API-Version', 'v1');
});

test('resolves version from header', function () {
    config(['apiroute.strategy' => 'header']);

    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['version' => 'v1']));
    });
    ApiRoute::version('v2', function () {
        Route::get('test', fn () => response()->json(['version' => 'v2']));
    });

    $response = $this->withHeader('X-API-Version', 'v2')->get('/api/test');

    $response->assertOk();
    $response->assertJson(['version' => 'v2']);
});

test('uses default version when none specified', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['version' => 'v1']));
    })->current();

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'active');
});

test('returns 404 for non-existent version', function () {
    config(['apiroute.fallback.enabled' => false]);

    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['version' => 'v1']));
    });

    $response = $this->get('/api/v99/test');

    $response->assertNotFound();
});
