<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\Contracts\ConsumerResolverInterface;
use Grazulex\ApiRoute\Contracts\ConsumerTrackerInterface;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Grazulex\ApiRoute\Tracking\ConsumerHasher;
use Grazulex\ApiRoute\VersionDefinition;
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

            // Resolved and hashed now, so the raw identifier never leaves the request
            $consumer = $this->resolveConsumer($request);

            // Track after response for performance
            dispatch(function () use ($version, $endpoint, $method, $statusCode, $consumer): void {
                $this->tracker->track(
                    version: $version,
                    endpoint: $endpoint,
                    method: $method,
                    status: $statusCode
                );

                if ($consumer !== null && $this->tracker instanceof ConsumerTrackerInterface) {
                    $this->tracker->trackConsumer(
                        version: $version,
                        consumer: $consumer,
                        status: $statusCode
                    );
                }
            })->afterResponse();
        }

        return $response;
    }

    /**
     * Return the hashed consumer identifier, or null when consumer tracking does not apply.
     */
    private function resolveConsumer(Request $request): ?string
    {
        if (config('apiroute.tracking.consumers.enabled', false) !== true) {
            return null;
        }

        if (! $this->tracker instanceof ConsumerTrackerInterface) {
            return null;
        }

        if (config('apiroute.tracking.consumers.only_deprecated', false) === true) {
            $definition = $request->attributes->get('api_version_definition');

            if (! $definition instanceof VersionDefinition || (! $definition->isDeprecated() && ! $definition->isSunset())) {
                return null;
            }
        }

        /** @var ConsumerHasher $hasher */
        $hasher = app(ConsumerHasher::class);

        if (! $hasher->canHash()) {
            return null;
        }

        /** @var ConsumerResolverInterface $resolver */
        $resolver = app(ConsumerResolverInterface::class);

        $identifier = $resolver->resolve($request);

        if ($identifier === null || $identifier === '') {
            return null;
        }

        return $hasher->hash($identifier);
    }
}
