<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\VersionDefinition;

if (! function_exists('api_version')) {
    /**
     * Get the current API version from the request.
     */
    function api_version(): ?string
    {
        return ApiRoute::resolveVersion(request());
    }
}

if (! function_exists('api_version_definition')) {
    /**
     * Get the current API version definition from the request.
     */
    function api_version_definition(): ?VersionDefinition
    {
        return request()->attributes->get('api_version_definition');
    }
}
