<?php

declare(strict_types=1);

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Support\Facades\Route;

test('routes can be registered with subdomain', function () {
    config([
        'apiroute.strategies.uri.domain' => 'api.example.com',
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('users', fn () => response()->json(['version' => 'v1']));
    });

    // Check that routes were registered
    $routes = Route::getRoutes();
    $route = $routes->getByName(null);

    // Find the route matching our pattern
    $found = false;
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'v1/users')) {
            $found = true;
            expect($route->getDomain())->toBe('api.example.com');
            break;
        }
    }

    expect($found)->toBeTrue();
});

test('routes work with empty prefix and subdomain', function () {
    config([
        'apiroute.strategies.uri.domain' => 'api.example.com',
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['version' => 'v1']));
    });

    // The route should be /v1/test (no /api prefix)
    $routes = Route::getRoutes();
    $found = false;
    foreach ($routes as $route) {
        if ($route->uri() === 'v1/test') {
            $found = true;
            expect($route->getDomain())->toBe('api.example.com');
            break;
        }
    }

    expect($found)->toBeTrue();
});

test('routes work with prefix and subdomain combined', function () {
    config([
        'apiroute.strategies.uri.domain' => 'api.example.com',
        'apiroute.strategies.uri.prefix' => 'v1-api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('users', fn () => response()->json(['version' => 'v1']));
    });

    // The route should be /v1-api/v1/users with domain
    $routes = Route::getRoutes();
    $found = false;
    foreach ($routes as $route) {
        if ($route->uri() === 'v1-api/v1/users') {
            $found = true;
            expect($route->getDomain())->toBe('api.example.com');
            break;
        }
    }

    expect($found)->toBeTrue();
});

test('routes work without domain (backward compatibility)', function () {
    config([
        'apiroute.strategies.uri.domain' => null,
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('users', fn () => response()->json(['version' => 'v1']));
    });

    // The route should be /api/v1/users without domain
    $routes = Route::getRoutes();
    $found = false;
    foreach ($routes as $route) {
        if ($route->uri() === 'api/v1/users') {
            $found = true;
            expect($route->getDomain())->toBeNull();
            break;
        }
    }

    expect($found)->toBeTrue();
});

test('empty string domain is treated as no domain', function () {
    config([
        'apiroute.strategies.uri.domain' => '',
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['version' => 'v1']));
    });

    $routes = Route::getRoutes();
    foreach ($routes as $route) {
        if ($route->uri() === 'api/v1/test') {
            expect($route->getDomain())->toBeNull();
            break;
        }
    }
});

test('non-uri strategy also supports domain', function () {
    config([
        'apiroute.strategy' => 'header',
        'apiroute.strategies.uri.domain' => 'api.example.com',
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('users', fn () => response()->json(['version' => 'v1']));
    });

    $routes = Route::getRoutes();
    $found = false;
    foreach ($routes as $route) {
        if ($route->uri() === 'users') {
            $found = true;
            expect($route->getDomain())->toBe('api.example.com');
            break;
        }
    }

    expect($found)->toBeTrue();
});
