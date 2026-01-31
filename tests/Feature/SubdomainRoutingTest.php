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

test('routes can be registered with multiple domains', function () {
    config([
        'apiroute.strategies.uri.domain' => ['api.main.com', 'api.backup.com', 'api.proxy.com'],
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('users', fn () => response()->json(['version' => 'v1']));
    });

    $routes = Route::getRoutes();

    // Collect all domains for our route
    $foundDomains = [];
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'v1/users')) {
            $foundDomains[] = $route->getDomain();
        }
    }

    expect($foundDomains)->toHaveCount(3)
        ->and($foundDomains)->toContain('api.main.com')
        ->and($foundDomains)->toContain('api.backup.com')
        ->and($foundDomains)->toContain('api.proxy.com');
});

test('multi-domain works with non-uri strategy', function () {
    config([
        'apiroute.strategy' => 'header',
        'apiroute.strategies.uri.domain' => ['api.main.com', 'api.backup.com'],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('test', fn () => response()->json(['version' => 'v1']));
    });

    $routes = Route::getRoutes();

    $foundDomains = [];
    foreach ($routes as $route) {
        if ($route->uri() === 'api/test') {
            $foundDomains[] = $route->getDomain();
        }
    }

    expect($foundDomains)->toHaveCount(2)
        ->and($foundDomains)->toContain('api.main.com')
        ->and($foundDomains)->toContain('api.backup.com');
});

test('empty array domain is treated as no domain', function () {
    config([
        'apiroute.strategies.uri.domain' => [],
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

test('array with empty strings is filtered', function () {
    config([
        'apiroute.strategies.uri.domain' => ['api.example.com', '', null],
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function () {
        Route::get('users', fn () => response()->json(['version' => 'v1']));
    });

    $routes = Route::getRoutes();

    $foundDomains = [];
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'v1/users')) {
            $foundDomains[] = $route->getDomain();
        }
    }

    // Should only have 1 valid domain (empty strings filtered out)
    expect($foundDomains)->toHaveCount(1)
        ->and($foundDomains)->toContain('api.example.com');
});
