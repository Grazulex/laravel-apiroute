<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Support\Facades\Route;

test('rate limit headers are added when rate limit is configured', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->rateLimit(100);

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('X-RateLimit-Limit', '100');
    $response->assertHeader('X-RateLimit-Remaining');
});

test('rate limit is enforced after max attempts', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    })->rateLimit(3);

    // First 3 requests should succeed
    for ($i = 0; $i < 3; $i++) {
        $response = $this->get('/api/v1/test');
        $response->assertOk();
    }

    // 4th request should be throttled
    $response = $this->get('/api/v1/test');
    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});

test('rate limit is not applied when not configured', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['ok' => true]));
    });

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    expect($response->headers->has('X-RateLimit-Limit'))->toBeFalse();
});

test('different versions have separate rate limits', function () {
    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['version' => 'v1']));
    })->rateLimit(2);

    ApiRoute::version('v2', function () {
        Route::get('test', fn () => response()->json(['version' => 'v2']));
    })->rateLimit(2);

    // Use up v1 rate limit
    for ($i = 0; $i < 2; $i++) {
        $this->get('/api/v1/test')->assertOk();
    }
    $this->get('/api/v1/test')->assertStatus(429);

    // v2 should still work
    $this->get('/api/v2/test')->assertOk();
});
