<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Events;

use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\Request;

final readonly class DeprecatedVersionAccessed
{
    public function __construct(
        public VersionDefinition $version,
        public Request $request
    ) {}
}
