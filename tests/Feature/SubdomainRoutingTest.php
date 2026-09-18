<?php

declare(strict_types=1);

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Support\Facades\Route;

test('routes can be registered with subdomain', function (): void {
    config([
        'apiroute.strategies.uri.domain' => 'api.example.com',
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('routes work with empty prefix and subdomain', function (): void {
    config([
        'apiroute.strategies.uri.domain' => 'api.example.com',
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('routes work with prefix and subdomain combined', function (): void {
    config([
        'apiroute.strategies.uri.domain' => 'api.example.com',
        'apiroute.strategies.uri.prefix' => 'v1-api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('routes work without domain (backward compatibility)', function (): void {
    config([
        'apiroute.strategies.uri.domain' => null,
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('empty string domain is treated as no domain', function (): void {
    config([
        'apiroute.strategies.uri.domain' => '',
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('non-uri strategy also supports domain', function (): void {
    config([
        'apiroute.strategy' => 'header',
        'apiroute.strategies.uri.domain' => 'api.example.com',
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('routes can be registered with multiple domains', function (): void {
    config([
        'apiroute.strategies.uri.domain' => ['api.main.com', 'api.backup.com', 'api.proxy.com'],
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('multi-domain works with non-uri strategy', function (): void {
    config([
        'apiroute.strategy' => 'header',
        'apiroute.strategies.uri.domain' => ['api.main.com', 'api.backup.com'],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('empty array domain is treated as no domain', function (): void {
    config([
        'apiroute.strategies.uri.domain' => [],
        'apiroute.strategies.uri.prefix' => 'api',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('array with empty strings is filtered', function (): void {
    config([
        'apiroute.strategies.uri.domain' => ['api.example.com', '', null],
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    ApiRoute::version('v1', function (): void {
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

test('named routes stay unique across multiple domains so route:cache does not fail', function (): void {
    $routesFile = tempnam(sys_get_temp_dir(), 'apiroute_') . '.php';
    file_put_contents($routesFile, <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/', fn () => response()->json(['ok' => true]))->name('root');
        Route::post('login', fn () => response()->json(['ok' => true]))->name('login');
        PHP);

    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => $routesFile,
                'name' => 'api.',
                'status' => 'active',
            ],
        ],
        'apiroute.strategies.uri.domain' => ['api.main.test', 'api.backup.test'],
        'apiroute.strategies.uri.prefix' => '',
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    unlink($routesFile);

    $routes = Route::getRoutes();
    $routes->refreshNameLookups();

    // The primary (first configured) domain keeps the exact configured name,
    // so route() calls made before this fix continue to resolve.
    expect($routes->getByName('api.root'))->not->toBeNull();
    expect($routes->getByName('api.root')->getDomain())->toBe('api.main.test');
    expect($routes->getByName('api.login'))->not->toBeNull();

    // The secondary domain must NOT reuse those names.
    $names = collect(iterator_to_array($routes))
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->values();

    expect($names->duplicates())->toBeEmpty();

    $backupRoute = collect(iterator_to_array($routes))
        ->first(fn ($route) => $route->getDomain() === 'api.backup.test' && str_contains((string) $route->getName(), 'root'));
    expect($backupRoute)->not->toBeNull();
    expect($backupRoute->getName())->not->toBe('api.root');

    // This is exactly what `php artisan route:cache` runs internally; it
    // throws a LogicException on duplicate route names.
    expect(fn () => $routes->toSymfonyRouteCollection())->not->toThrow(LogicException::class);
});
