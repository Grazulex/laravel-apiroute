<?php

declare(strict_types=1);

use Grazulex\ApiRoute\ApiRouteManager;

/**
 * Tests that simulate real application boot scenarios.
 *
 * These tests verify that when the ServiceProvider boots with config-based
 * versions, routes are properly registered with middleware.
 */
test('service provider boot loads config-based versions and registers routes', function () {
    // First, ensure clean state
    $manager = app(ApiRouteManager::class);
    $manager->reset();

    // Setup config BEFORE boot (simulating real app config)
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'active',
            ],
        ],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    // Simulate what ServiceProvider::boot() does
    $manager->boot();

    // Verify version is loaded
    expect($manager->hasVersion('v1'))->toBeTrue();

    // Make HTTP request - should have headers
    $response = $this->get('/api/v1/test');

    $response->assertOk();
    $response->assertHeader('X-API-Version', 'v1');
});

test('routes registered via config have api.version middleware', function () {
    $manager = app(ApiRouteManager::class);
    $manager->reset();

    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'active',
            ],
        ],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager->boot();

    // Get the registered routes
    $routes = app('router')->getRoutes();
    $testRoute = $routes->getByName(null); // Get routes without names

    // Find our test route
    $found = false;
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'v1/test')) {
            $found = true;
            $middleware = $route->gatherMiddleware();
            expect($middleware)->toContain('api.version');
            break;
        }
    }

    expect($found)->toBeTrue('Route v1/test should be registered');
});

test('multiple test runs maintain separate config states', function () {
    // First run: v1 active
    $manager = app(ApiRouteManager::class);
    $manager->reset();

    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'active',
            ],
        ],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager->boot();

    $response = $this->get('/api/v1/test');
    $response->assertHeader('X-API-Version-Status', 'active');

    // Simulate "next test" - reset and reconfigure as deprecated
    $manager->reset();

    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => __DIR__ . '/../fixtures/v1-routes.php',
                'status' => 'deprecated',
                'deprecated_at' => '2025-01-01',
            ],
        ],
    ]);

    $manager->boot();

    // This should now show deprecated
    expect($manager->getVersion('v1')->isDeprecated())->toBeTrue();
});
