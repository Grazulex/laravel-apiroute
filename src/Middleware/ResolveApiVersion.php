<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\Contracts\VersionResolverInterface;
use Grazulex\ApiRoute\Events\DeprecatedVersionAccessed;
use Grazulex\ApiRoute\Exceptions\VersionNotFoundException;
use Grazulex\ApiRoute\Exceptions\VersionSunsetException;
use Grazulex\ApiRoute\Http\Headers\VersionHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiVersion
{
    public function __construct(
        private readonly VersionResolverInterface $resolver,
        private readonly VersionHeaders $headers
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

        // 3. Check if sunset
        /** @var string $sunsetAction */
        $sunsetAction = config('apiroute.sunset.action', 'reject');

        if ($version->isSunset() && $sunsetAction === 'reject') {
            throw new VersionSunsetException($version);
        }

        // 4. Store the version in the request
        $request->attributes->set('api_version', $version->name());
        $request->attributes->set('api_version_definition', $version);

        // 5. Dispatch event if deprecated version
        if ($version->isDeprecated()) {
            event(new DeprecatedVersionAccessed($version, $request));
        }

        // 6. Execute the request
        $response = $next($request);

        // 7. Add version headers
        return $this->headers->addToResponse($response, $version, $request);
    }
}
