<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackApiUsage
{
    public function __construct(
        private readonly VersionTrackerInterface $tracker
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /** @var string|null $version */
        $version = $request->attributes->get('api_version');

        if ($version !== null) {
            // Extract primitive values before dispatch to avoid serialization issues
            // The request contains VersionDefinition with Closure properties that cannot be serialized
            $endpoint = $request->path();
            $method = $request->method();
            $statusCode = $response->getStatusCode();

            // Track after response for performance
            dispatch(function () use ($version, $endpoint, $method, $statusCode): void {
                $this->tracker->track(
                    version: $version,
                    endpoint: $endpoint,
                    method: $method,
                    status: $statusCode
                );
            })->afterResponse();
        }

        return $response;
    }
}
