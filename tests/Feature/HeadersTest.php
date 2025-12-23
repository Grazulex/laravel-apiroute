<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Support\Facades\Route;

test('adds version header to response', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    });

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('X-API-Version', 'v1');
});

test('adds status header to response', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->current();

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('X-API-Version-Status', 'active');
});

test('adds deprecation header for deprecated version', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->deprecated('2025-06-01');

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('Deprecation');
    $response->assertHeader('X-API-Version-Status', 'deprecated');
});

test('adds sunset header when sunset date is set', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->deprecated('2025-06-01')->sunset('2099-12-01');

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('Sunset');
});

test('adds link header for successor version', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->deprecated('2025-06-01')->setSuccessor('v2');

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('Link');
    expect($response->headers->get('Link'))->toContain('successor-version');
});

test('headers can be disabled', function () {
    config(['apiroute.headers.enabled' => false]);

    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->deprecated('2025-06-01');

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    expect($response->headers->has('X-API-Version'))->toBeFalse();
    expect($response->headers->has('Deprecation'))->toBeFalse();
});
