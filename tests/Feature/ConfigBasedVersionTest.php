<?php

declare(strict_types=1);

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Define versions via configuration
    config([
        'apiroute.versions' => [
            'v1' => [
                'routes' => null, // We'll define routes inline for testing
                'middleware' => [],
                'status' => 'active',
            ],
        ],
    ]);

    // Reset and reload the manager to pick up the new config
    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->boot();

    // Register a test route for v1
    Route::prefix('api/v1')
        ->middleware(['api', 'api.version'])
        ->group(function () {
            Route::get('config-test', fn () => response()->json(['source' => 'config', 'version' => 'v1']));
        });
});

test('config-based version is available in first test', function () {
    expect(ApiRoute::hasVersion('v1'))->toBeTrue();
    expect(ApiRoute::getVersion('v1'))->not->toBeNull();
    expect(ApiRoute::getVersion('v1')->isActive())->toBeTrue();
});

test('config-based version is still available in second test', function () {
    // This test validates the fix for issue #10
    // Previously, versions would be lost between tests
    expect(ApiRoute::hasVersion('v1'))->toBeTrue();
    expect(ApiRoute::getVersion('v1'))->not->toBeNull();
});

test('config-based version is still available in third test', function () {
    expect(ApiRoute::hasVersion('v1'))->toBeTrue();
});

test('can access routes defined via config across tests', function () {
    $response = $this->get('/api/v1/config-test');

    $response->assertOk();
    $response->assertJson(['source' => 'config', 'version' => 'v1']);
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
