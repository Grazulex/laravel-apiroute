<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Exceptions\VersionNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceApiVersion
{
    public function __construct(
        private readonly ApiRouteManager $manager
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $version): Response
    {
        $versionDefinition = $this->manager->getVersion($version);

        if ($versionDefinition === null) {
            throw new VersionNotFoundException($version);
        }

        $request->attributes->set('api_version', $version);
        $request->attributes->set('api_version_definition', $versionDefinition);

        return $next($request);
    }
}
