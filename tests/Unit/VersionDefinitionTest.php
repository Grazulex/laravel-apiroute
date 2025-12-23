<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Support\VersionStatus;
use Grazulex\ApiRoute\VersionDefinition;

test('can create a version definition', function () {
    $definition = new VersionDefinition('v1', fn () => null);

    expect($definition->name())->toBe('v1');
    expect($definition->isActive())->toBeTrue();
    expect($definition->isUsable())->toBeTrue();
});

test('can mark version as current', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->current();

    expect($definition->status())->toBe(VersionStatus::Active);
    expect($definition->isActive())->toBeTrue();
});

test('can mark version as beta', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->beta();

    expect($definition->status())->toBe(VersionStatus::Beta);
    expect($definition->isBeta())->toBeTrue();
    expect($definition->isUsable())->toBeTrue();
});

test('can mark version as deprecated with string date', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->deprecated('2025-06-01');

    expect($definition->status())->toBe(VersionStatus::Deprecated);
    expect($definition->isDeprecated())->toBeTrue();
    expect($definition->deprecationDate())->toBeInstanceOf(Carbon::class);
    expect($definition->deprecationDate()->format('Y-m-d'))->toBe('2025-06-01');
});

test('can mark version as deprecated with carbon date', function () {
    $date = Carbon::parse('2025-06-01');
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->deprecated($date);

    expect($definition->deprecationDate())->toBe($date);
});

test('can set sunset date', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->deprecated('2025-06-01')->sunset('2099-12-01');

    expect($definition->sunsetDate())->toBeInstanceOf(Carbon::class);
    expect($definition->sunsetDate()->format('Y-m-d'))->toBe('2099-12-01');
    expect($definition->isSunset())->toBeFalse(); // Date is in the future
});

test('is sunset when sunset date is in the past', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->sunset(Carbon::now()->subDay());

    expect($definition->isSunset())->toBeTrue();
    expect($definition->isUsable())->toBeFalse();
});

test('can set successor version', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->setSuccessor('v2');

    expect($definition->successor())->toBe('v2');
});

test('can set documentation url', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->documentation('https://docs.example.com/v1');

    expect($definition->documentationUrl())->toBe('https://docs.example.com/v1');
});

test('can set rate limit', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->rateLimit(100);

    expect($definition->rateLimit_())->toBe(100);
});

test('can set middleware', function () {
    $definition = new VersionDefinition('v1', fn () => null);
    $definition->middleware(['auth', 'throttle']);

    expect($definition->middlewares())->toBe(['auth', 'throttle']);
});

test('fluent api returns self', function () {
    $definition = new VersionDefinition('v1', fn () => null);

    expect($definition->current())->toBe($definition);
    expect($definition->beta())->toBe($definition);
    expect($definition->deprecated('2025-06-01'))->toBe($definition);
    expect($definition->sunset('2025-12-01'))->toBe($definition);
    expect($definition->setSuccessor('v2'))->toBe($definition);
    expect($definition->documentation('https://example.com'))->toBe($definition);
    expect($definition->rateLimit(100))->toBe($definition);
    expect($definition->middleware('auth'))->toBe($definition);
});
