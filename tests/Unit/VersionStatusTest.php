<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Support\VersionStatus;

test('active status is usable', function () {
    expect(VersionStatus::Active->isUsable())->toBeTrue();
});

test('beta status is usable', function () {
    expect(VersionStatus::Beta->isUsable())->toBeTrue();
});

test('deprecated status is usable', function () {
    expect(VersionStatus::Deprecated->isUsable())->toBeTrue();
});

test('sunset status is not usable', function () {
    expect(VersionStatus::Sunset->isUsable())->toBeFalse();
});

test('status has correct labels', function () {
    expect(VersionStatus::Active->label())->toBe('Active');
    expect(VersionStatus::Beta->label())->toBe('Beta');
    expect(VersionStatus::Deprecated->label())->toBe('Deprecated');
    expect(VersionStatus::Sunset->label())->toBe('Sunset');
});

test('status has correct values', function () {
    expect(VersionStatus::Active->value)->toBe('active');
    expect(VersionStatus::Beta->value)->toBe('beta');
    expect(VersionStatus::Deprecated->value)->toBe('deprecated');
    expect(VersionStatus::Sunset->value)->toBe('sunset');
});
