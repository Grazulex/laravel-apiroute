<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Support;

enum VersionStatus: string
{
    case Active = 'active';
    case Beta = 'beta';
    case Deprecated = 'deprecated';
    case Sunset = 'sunset';

    public function isUsable(): bool
    {
        return match ($this) {
            self::Active, self::Beta, self::Deprecated => true,
            self::Sunset => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Beta => 'Beta',
            self::Deprecated => 'Deprecated',
            self::Sunset => 'Sunset',
        };
    }
}
