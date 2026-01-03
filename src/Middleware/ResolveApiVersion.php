<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\Contracts\VersionResolverInterface;
use Grazulex\ApiRoute\Events\DeprecatedVersionAccessed;
use Grazulex\ApiRoute\Exceptions\VersionNotFoundException;
use Grazulex\ApiRoute\Exceptions\VersionSunsetException;
use Grazulex\ApiRoute\Support\ApiVersionContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiVersion
{
    public function __construct(
        private readonly VersionResolverInterface $resolver,
        private readonly ApiVersionContext $context
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Resolve the requested version
        $version = $this->resolver->resolve($request);

        // 2. Verify that the version exists
        if ($version === null) {
            $requestedVersion = $this->resolver->getRequestedVersion($request) ?? 'unknown';
            throw new VersionNotFoundException($requestedVersion);
        }

        // 3. Store version in context early for the response listener
        // This ensures headers are added to ALL responses including error responses
        // (including VersionSunsetException responses)
        $this->context->set($version, $request);

        // 4. Store the version in the request
        $request->attributes->set('api_version', $version->name());
        $request->attributes->set('api_version_definition', $version);

        // 5. Check if sunset
        /** @var string $sunsetAction */
        $sunsetAction = config('apiroute.sunset.action', 'reject');

        if ($version->isSunset() && $sunsetAction === 'reject') {
            throw new VersionSunsetException($version);
        }

        // 6. Dispatch event if deprecated version
        if ($version->isDeprecated()) {
            event(new DeprecatedVersionAccessed($version, $request));
        }

        // 7. Execute the request
        // Headers will be added by the AddVersionHeadersToResponse listener
        return $next($request);
    }
}
