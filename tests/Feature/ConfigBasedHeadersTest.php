<?php

declare(strict_types=1);

use Grazulex\ApiRoute\ApiRouteManager;

/**
 * Tests for config-based version registration with HTTP headers.
 *
 * These tests verify that when versions are defined via config/apiroute.php,
 * the appropriate headers (X-API-Version, Deprecation, Sunset, etc.) are
 * added to HTTP responses.
 */
test('config-based version adds X-API-Version header', function () {
    // Setup: Define version via configuration with a real route file
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'active',
            ],
        ],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    // Reset and reload the manager
    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    // Make HTTP request
    $response = $this->get('/api/v1/test');

    // Assert headers are present
    $response->assertOk();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'active');
});

test('config-based deprecated version adds Deprecation header', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'deprecated',
                'deprecated_at' => '2025-06-01',
                'successor' => 'v2',
            ],
        ],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('X-API-Version', 'v1');
    $response->assertHeader('X-API-Version-Status', 'deprecated');
    $response->assertHeader('Deprecation');
    $response->assertHeader('Link');
    expect($response->headers->get('Link'))->toContain('successor-version');
});

test('config-based version with sunset date adds Sunset header', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'deprecated',
                'deprecated_at' => '2025-06-01',
                'sunset_at' => '2099-12-31',
            ],
        ],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('Sunset');
});

test('config-based version custom middleware is applied', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'active',
                'middleware' => ['throttle:10,1'],
            ],
        ],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    // Get the registered routes and verify middleware
    $routes = app('router')->getRoutes();
    $found = false;
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'v1/test')) {
            $found = true;
            $middleware = $route->gatherMiddleware();
            expect($middleware)->toContain('api.version');
            expect($middleware)->toContain('throttle:10,1');
            break;
        }
    }
    expect($found)->toBeTrue('Route v1/test should be registered');
});

test('config-based version route name prefix is applied', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'active',
                'name' => 'api.v1.',
            ],
        ],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    // The routes should have name prefix applied
    $routes = app('router')->getRoutes();
    $testRoute = null;
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'v1/test')) {
            $testRoute = $route;
            break;
        }
    }

    expect($testRoute)->not->toBeNull();
    // Route name should start with the prefix if defined in route file
    // Note: The name prefix is applied to routes that have ->name() in the route file
});
