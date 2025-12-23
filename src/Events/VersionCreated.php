<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Events;

use Grazulex\ApiRoute\VersionDefinition;

final readonly class VersionCreated
{
    public function __construct(
        public VersionDefinition $version
    ) {}
}
