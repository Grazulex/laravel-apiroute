<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Attributes\Deprecated;
use Grazulex\ApiRoute\Support\EndpointLifecycle;

test('fromArray parses dates and keeps strings', function (): void {
    $lifecycle = EndpointLifecycle::fromArray([
        'since' => '2026-06-01',
        'sunset' => '2026-12-01',
        'successor' => 'api.v2.users.index',
        'docs' => 'https://docs.example.com/migrate',
        'reason' => 'Use v2',
    ]);

    expect($lifecycle->deprecatedAt?->toDateString())->toBe('2026-06-01')
        ->and($lifecycle->sunsetAt?->toDateString())->toBe('2026-12-01')
        ->and($lifecycle->successor)->toBe('api.v2.users.index')
        ->and($lifecycle->docs)->toBe('https://docs.example.com/migrate')
        ->and($lifecycle->reason)->toBe('Use v2');
});

test('fromArray tolerates missing keys', function (): void {
    $lifecycle = EndpointLifecycle::fromArray(['since' => '2026-06-01']);

    expect($lifecycle->sunsetAt)->toBeNull()
        ->and($lifecycle->successor)->toBeNull()
        ->and($lifecycle->isDeprecated())->toBeTrue()
        ->and($lifecycle->isSunset())->toBeFalse();
});

test('fromAttribute mirrors the attribute fields', function (): void {
    $lifecycle = EndpointLifecycle::fromAttribute(new Deprecated(since: '2026-06-01', successor: '/api/v2/users'));

    expect($lifecycle->deprecatedAt?->toDateString())->toBe('2026-06-01')
        ->and($lifecycle->successor)->toBe('/api/v2/users');
});

test('merge lets non-null override fields win', function (): void {
    $class = EndpointLifecycle::fromArray(['since' => '2026-01-01', 'successor' => 'class-successor', 'docs' => 'https://class']);
    $method = EndpointLifecycle::fromArray(['sunset' => '2026-12-01', 'successor' => 'method-successor']);

    $merged = $class->merge($method);

    expect($merged->deprecatedAt?->toDateString())->toBe('2026-01-01')
        ->and($merged->sunsetAt?->toDateString())->toBe('2026-12-01')
        ->and($merged->successor)->toBe('method-successor')
        ->and($merged->docs)->toBe('https://class');
});

test('isSunset compares against now inclusively', function (): void {
    $lifecycle = EndpointLifecycle::fromArray(['sunset' => '2026-12-01 00:00:00']);

    expect($lifecycle->isSunset(Carbon::parse('2026-11-30 23:59:59')))->toBeFalse()
        ->and($lifecycle->isSunset(Carbon::parse('2026-12-01 00:00:00')))->toBeTrue()
        ->and($lifecycle->isSunset(Carbon::parse('2027-01-01')))->toBeTrue();
});

test('isDeprecated is false when nothing is set', function (): void {
    expect(EndpointLifecycle::fromArray([])->isDeprecated())->toBeFalse();
});
