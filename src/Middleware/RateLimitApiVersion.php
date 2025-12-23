<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitApiVersion
{
    public function __construct(
        private readonly RateLimiter $limiter
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var VersionDefinition|null $version */
        $version = $request->attributes->get('api_version_definition');

        if ($version === null) {
            return $next($request);
        }

        $maxAttempts = $version->rateLimit_();

        if ($maxAttempts === null) {
            return $next($request);
        }

        $key = $this->resolveRequestSignature($request, $version);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            throw new ThrottleRequestsException(
                message: 'Too Many Attempts.',
                headers: $this->getHeaders($maxAttempts, 0, $retryAfter)
            );
        }

        $this->limiter->hit($key, 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    private function resolveRequestSignature(Request $request, VersionDefinition $version): string
    {
        $identifier = $request->user()?->getAuthIdentifier()
            ?? $request->ip()
            ?? 'unknown';

        return 'apiroute:' . $version->name() . ':' . sha1((string) $identifier);
    }

    private function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        $remaining = $maxAttempts - $this->limiter->attempts($key);

        return max(0, $remaining);
    }

    /**
     * @return array<string, int|string>
     */
    private function getHeaders(int $maxAttempts, int $remainingAttempts, ?int $retryAfter = null): array
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if ($retryAfter !== null) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = time() + $retryAfter;
        }

        return $headers;
    }

    private function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add($this->getHeaders($maxAttempts, $remainingAttempts));

        return $response;
    }
}
