<?php

declare(strict_types=1);

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Facades\ApiRoute;

test('config-based version is loaded from configuration', function () {
    // Setup: Define version via configuration
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null,
                'middleware' => [],
                'status' => 'active',
            ],
        ],
    ]);

    // Reset and reload the manager to pick up the new config
    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    // Assert
    expect(ApiRoute::hasVersion('v1'))->toBeTrue();
    expect(ApiRoute::getVersion('v1'))->not->toBeNull();
    expect(ApiRoute::getVersion('v1')->isActive())->toBeTrue();
});

test('multiple versions can be defined via config', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null,
                'status' => 'deprecated',
                'deprecated_at' => '2025-01-01',
                'successor' => 'v2',
            ],
            'v2' => [
                'routes' => null,
                'status' => 'active',
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    expect(ApiRoute::hasVersion('v1'))->toBeTrue();
    expect(ApiRoute::hasVersion('v2'))->toBeTrue();
    expect(ApiRoute::getVersion('v1')->isDeprecated())->toBeTrue();
    expect(ApiRoute::getVersion('v2')->isActive())->toBeTrue();
    expect(ApiRoute::getVersion('v1')->successor())->toBe('v2');
});

test('version with rate limit from config', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null,
                'status' => 'active',
                'rate_limit' => 100,
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    expect(ApiRoute::getVersion('v1')->rateLimit_())->toBe(100);
});

test('version with documentation url from config', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null,
                'status' => 'active',
                'documentation' => 'https://docs.example.com/v1',
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    expect(ApiRoute::getVersion('v1')->documentationUrl())->toBe('https://docs.example.com/v1');
});

test('version with beta status from config', function () {
    config([
        'apiroute.versions' => [
            'v3' => [
                'routes' => null,
                'status' => 'beta',
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    expect(ApiRoute::getVersion('v3'))->not->toBeNull();
    expect(ApiRoute::getVersion('v3')->isBeta())->toBeTrue();
});

test('version with sunset status from config', function () {
    config([
        'apiroute.versions' => [
            'v0' => [
                'routes' => null,
                'status' => 'sunset',
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    expect(ApiRoute::getVersion('v0'))->not->toBeNull();
    expect(ApiRoute::getVersion('v0')->isSunset())->toBeTrue();
});

test('version with sunset date from config', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null,
                'status' => 'deprecated',
                'deprecated_at' => '2024-01-01',
                'sunset_at' => '2024-06-01',
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    $version = ApiRoute::getVersion('v1');
    expect($version)->not->toBeNull();
    expect($version->isDeprecated())->toBeTrue();
    expect($version->sunsetDate())->not->toBeNull();
    expect($version->isSunset())->toBeTrue(); // Past date should be sunset
});

test('version with middleware from config', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null,
                'status' => 'active',
                'middleware' => ['auth:sanctum', 'verified'],
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    $version = ApiRoute::getVersion('v1');
    expect($version)->not->toBeNull();
    expect($version->middlewares())->toBe(['auth:sanctum', 'verified']);
});

test('empty versions config does not break boot', function () {
    config([
        'apiroute.versions' => [],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    expect(ApiRoute::versions()->count())->toBe(0);
});

test('boot is idempotent', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null,
                'status' => 'active',
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();

    // Boot multiple times
    $manager->boot();
    $manager->boot();
    $manager->boot();

    // Should still have only one v1 version
    expect(ApiRoute::versions()->count())->toBe(1);
    expect(ApiRoute::hasVersion('v1'))->toBeTrue();
});

test('reset clears all versions', function () {
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null,
                'status' => 'active',
            ],
        ],
    ]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    expect(ApiRoute::hasVersion('v1'))->toBeTrue();

    // Reset should clear versions
    $manager->reset();
    expect(ApiRoute::hasVersion('v1'))->toBeFalse();
    expect(ApiRoute::versions()->count())->toBe(0);
});
