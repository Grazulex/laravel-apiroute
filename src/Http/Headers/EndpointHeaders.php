<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Headers;

use Carbon\Carbon;
use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds endpoint-level deprecation headers. Called after VersionHeaders so
 * that the more specific endpoint values replace the version ones.
 */
final class EndpointHeaders
{
    public function __construct(private readonly EndpointLifecycleResolver $resolver) {}

    public function addToResponse(Response $response, EndpointLifecycle $lifecycle, ?Route $route = null): Response
    {
        /** @var array<string, mixed> $config */
        $config = config('apiroute.headers', []);

        if (($config['enabled'] ?? true) === false) {
            return $response;
        }

        /** @var array<string, bool> $include */
        $include = $config['include'] ?? [];

        if (($include['deprecation'] ?? true) && $lifecycle->deprecatedAt instanceof Carbon) {
            $response->headers->set('Deprecation', $lifecycle->deprecatedAt->format(Carbon::RFC7231));
        }

        if (($include['sunset'] ?? true) && $lifecycle->sunsetAt instanceof Carbon) {
            $response->headers->set('Sunset', $lifecycle->sunsetAt->format(Carbon::RFC7231));
        }

        if ($include['successor_link'] ?? true) {
            $links = [];
            $successorUrl = $this->resolver->resolveSuccessorUrl($lifecycle, $route);

            if ($successorUrl !== null) {
                $links[] = "<{$successorUrl}>; rel=\"successor-version\"";
            }

            if ($lifecycle->docs !== null && $lifecycle->docs !== '') {
                $links[] = "<{$lifecycle->docs}>; rel=\"deprecation\"";
            }

            if ($links !== []) {
                $response->headers->set('Link', implode(', ', $links));
            }
        }

        if ($include['endpoint_status'] ?? true) {
            $response->headers->set('X-API-Endpoint-Status', $lifecycle->isSunset() ? 'sunset' : 'deprecated');
        }

        return $response;
    }
}
