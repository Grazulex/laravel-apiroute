<?php

declare(strict_types=1);

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\VersionResolver;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->config = [
        'strategy' => 'uri',
        'strategies' => [
            'uri' => [
                'prefix' => 'api',
                'pattern' => 'v{version}',
            ],
            'header' => [
                'name' => 'X-API-Version',
            ],
            'query' => [
                'parameter' => 'api_version',
            ],
            'accept' => [
                'vendor' => 'myapi',
            ],
        ],
        'default_version' => 'latest',
        'fallback' => [
            'enabled' => true,
            'strategy' => 'previous',
        ],
    ];
});

test('resolves version from uri path', function () {
    $manager = new ApiRouteManager($this->config);
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null);

    $resolver = new VersionResolver($manager, $this->config);

    $request = Request::create('/api/v1/users', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v1');
});

test('resolves version from header', function () {
    $config = array_merge($this->config, ['strategy' => 'header']);
    $manager = new ApiRouteManager($config);
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null);

    $resolver = new VersionResolver($manager, $config);

    $request = Request::create('/api/users', 'GET');
    $request->headers->set('X-API-Version', 'v2');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v2');
});

test('resolves version from query parameter', function () {
    $config = array_merge($this->config, ['strategy' => 'query']);
    $manager = new ApiRouteManager($config);
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null);

    $resolver = new VersionResolver($manager, $config);

    $request = Request::create('/api/users?api_version=v1', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v1');
});

test('resolves version from accept header', function () {
    $config = array_merge($this->config, ['strategy' => 'accept']);
    $manager = new ApiRouteManager($config);
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null);

    $resolver = new VersionResolver($manager, $config);

    $request = Request::create('/api/users', 'GET');
    $request->headers->set('Accept', 'application/vnd.myapi.v2+json');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v2');
});

test('returns null for non-existent version', function () {
    $config = array_merge($this->config, ['fallback' => ['enabled' => false]]);
    $manager = new ApiRouteManager($config);
    $manager->version('v1', fn () => null);

    $resolver = new VersionResolver($manager, $config);

    $request = Request::create('/api/v99/users', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->toBeNull();
});

test('falls back to previous version when enabled', function () {
    $manager = new ApiRouteManager($this->config);
    $manager->version('v1', fn () => null)->current();
    // v2 doesn't exist

    $resolver = new VersionResolver($manager, $this->config);

    $request = Request::create('/api/v2/users', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v1');
});

test('returns default version when none specified', function () {
    $manager = new ApiRouteManager($this->config);
    $manager->version('v1', fn () => null);
    $manager->version('v2', fn () => null)->current();

    $resolver = new VersionResolver($manager, $this->config);

    $request = Request::create('/api/users', 'GET');
    $version = $resolver->resolve($request);

    expect($version)->not->toBeNull();
    expect($version->name())->toBe('v2');
});

test('getRequestedVersion returns raw version string', function () {
    $manager = new ApiRouteManager($this->config);
    $resolver = new VersionResolver($manager, $this->config);

    $request = Request::create('/api/v1/users', 'GET');
    $versionString = $resolver->getRequestedVersion($request);

    expect($versionString)->toBe('v1');
});
