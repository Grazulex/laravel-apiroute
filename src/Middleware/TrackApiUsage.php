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
            // Track after response for performance
            dispatch(function () use ($request, $response, $version): void {
                $this->tracker->track(
                    version: $version,
                    endpoint: $request->path(),
                    method: $request->method(),
                    status: $response->getStatusCode()
                );
            })->afterResponse();
        }

        return $response;
    }
}
