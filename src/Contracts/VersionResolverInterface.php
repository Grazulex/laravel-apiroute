<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Contracts;

use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\Request;

interface VersionResolverInterface
{
    /**
     * Resolve the API version from the request.
     */
    public function resolve(Request $request): ?VersionDefinition;

    /**
     * Get the raw requested version string from the request.
     */
    public function getRequestedVersion(Request $request): ?string;
}
