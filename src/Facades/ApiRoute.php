<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Facades;

use Closure;
use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static VersionDefinition version(string $version, Closure $routes)
 * @method static Collection<string, VersionDefinition> versions()
 * @method static VersionDefinition|null getVersion(string $version)
 * @method static VersionDefinition|null currentVersion()
 * @method static string|null resolveVersion(Request $request)
 * @method static bool hasVersion(string $version)
 * @method static bool isDeprecated(string $version)
 * @method static bool isSunset(string $version)
 * @method static bool isActive(string $version)
 *
 * @see \Grazulex\ApiRoute\ApiRouteManager
 */
class ApiRoute extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ApiRouteManager::class;
    }
}
