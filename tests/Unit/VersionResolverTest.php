<?php

declare(strict_types=1);

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\VersionResolver;
use Illuminate\Http\Request;

beforeEach(function () {
    // Set default config values
    config([
        'apiroute.strategy' => 'uri',
        'apiroute.strategies.uri.prefix' => 'api',
        'apiroute.strategies.uri.pattern' => 'v{version}',
        'apiroute.strategies.header.name' => 'X-API-Version',
        'apiroute.strategies.query.parameter' => 'api_version',
        'apiroute.strategies.accept.vendor' => 'myapi',
        'apiroute.default_version' => 'latest',
        'apiroute.fallback.enabled' => true,
        'apiroute.fallback.strategy' => 'previous',
    ]);
});

test('resolves version from uri path', function () {
    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null);

    $resolver = new VersionResolver($manager);

    $request = Request::create('/api/v1/users', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v1');
});

test('resolves version from header', function () {
    config(['apiroute.strategy' => 'header']);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null);

    $resolver = new VersionResolver($manager);

    $request = Request::create('/api/users', 'GET');
    $request->headers->set('X-API-Version', 'v2');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v2');
});

test('resolves version from query parameter', function () {
    config(['apiroute.strategy' => 'query']);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null);

    $resolver = new VersionResolver($manager);

    $request = Request::create('/api/users?api_version=v1', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v1');
});

test('resolves version from accept header', function () {
    config(['apiroute.strategy' => 'accept']);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null);

    $resolver = new VersionResolver($manager);

    $request = Request::create('/api/users', 'GET');
    $request->headers->set('Accept', 'application/vnd.myapi.v2+json');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v2');
});

test('returns null for non-existent version', function () {
    config(['apiroute.fallback.enabled' => false]);

    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->version('v1', fn () => null);

    $resolver = new VersionResolver($manager);

    $request = Request::create('/api/v99/users', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->toBeNull();
});

test('falls back to previous version when enabled', function () {
    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->version('v1', fn () => null)->current();
    // v2 doesn't exist

    $resolver = new VersionResolver($manager);

    $request = Request::create('/api/v2/users', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v1');
});

test('returns default version when none specified', function () {
    $manager = app(ApiRouteManager::class);
    $manager->reset();
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null)->current();

    $resolver = new VersionResolver($manager);

    $request = Request::create('/api/users', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v2');
});

test('getRequestedVersion returns raw version string', function () {
    $manager = app(ApiRouteManager::class);
    $manager->reset();

    $resolver = new VersionResolver($manager);

    $request = Request::create('/api/v1/users', 'GET');
    $versionString = $resolver->getRequestedVersion($request);

    expect($versionString)->toBe('v1');
});
