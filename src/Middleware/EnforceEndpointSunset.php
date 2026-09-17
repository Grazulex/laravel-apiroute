<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\Exceptions\EndpointSunsetException;
use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceEndpointSunset
{
    public function __construct(private readonly EndpointLifecycleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $lifecycle = $this->resolver->forRequest($request);

        if ($lifecycle instanceof EndpointLifecycle
            && $lifecycle->isSunset()
            && config('apiroute.sunset.action', 'reject') === 'reject') {
            throw new EndpointSunsetException($lifecycle, $this->resolver->resolveSuccessorUrl($lifecycle));
        }

        return $next($request);
    }
}
