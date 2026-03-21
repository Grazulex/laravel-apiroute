<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Support\Facades\Route;

test('can register version via facade', function (): void {
    $definition = ApiRoute::version('v1', function (): void {
        Route::get('test', fn (): string => 'test');
    });

    expect($definition)->toBeInstanceOf(VersionDefinition::class);
    expect($definition->name())->toBe('v1');
});

test('can get all versions', function (): void {
    ApiRoute::version('v1', fn (): null => null);
    ApiRoute::version('v2', fn (): null => null);

    $versions = ApiRoute::versions();

    expect($versions)->toHaveCount(2);
    expect($versions->keys()->toArray())->toBe(['v1', 'v2']);
});

test('can get specific version', function (): void {
    ApiRoute::version('v1', fn (): null => null);

    $version = ApiRoute::getVersion('v1');

    expect($version)->toBeInstanceOf(VersionDefinition::class);
    expect($version->name())->toBe('v1');
});

test('returns null for non-existent version', function (): void {
    $version = ApiRoute::getVersion('v99');

    expect($version)->toBeNull();
});

test('can check if version exists', function (): void {
    ApiRoute::version('v1', fn (): null => null);

    expect(ApiRoute::hasVersion('v1'))->toBeTrue();
    expect(ApiRoute::hasVersion('v99'))->toBeFalse();
});

test('can check if version is deprecated', function (): void {
    ApiRoute::version('v1', fn (): null => null)->deprecated('2025-06-01');
    ApiRoute::version('v2', fn (): null => null)->current();

    expect(ApiRoute::isDeprecated('v1'))->toBeTrue();
    expect(ApiRoute::isDeprecated('v2'))->toBeFalse();
});

test('can check if version is active', function (): void {
    ApiRoute::version('v1', fn (): null => null)->deprecated('2025-06-01');
    ApiRoute::version('v2', fn (): null => null)->current();

    expect(ApiRoute::isActive('v1'))->toBeFalse();
    expect(ApiRoute::isActive('v2'))->toBeTrue();
});

test('can get current version', function (): void {
    ApiRoute::version('v1', fn (): null => null)->deprecated('2025-06-01');
    ApiRoute::version('v2', fn (): null => null)->current();

    $current = ApiRoute::currentVersion();

    expect($current)->toBeInstanceOf(VersionDefinition::class);
    expect($current->name())->toBe('v2');
});
