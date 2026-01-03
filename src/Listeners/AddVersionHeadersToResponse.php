<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Listeners;

use Grazulex\ApiRoute\Contracts\VersionResolverInterface;
use Grazulex\ApiRoute\Http\Headers\VersionHeaders;
use Grazulex\ApiRoute\Support\ApiVersionContext;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;

/**
 * Listener that adds API version headers to all responses.
 *
 * This listener is triggered after the response is created but before it's sent,
 * ensuring that version headers are added to ALL responses including error responses
 * (401, 403, 404, 500, etc.).
 */
class AddVersionHeadersToResponse
{
    public function __construct(
        private readonly ApiVersionContext $context,
        private readonly VersionHeaders $headers,
        private readonly VersionResolverInterface $resolver
    ) {}

    /**
     * Handle the RequestHandled event.
     */
    public function handle(RequestHandled $event): void
    {
        $request = $event->request;
        $version = $this->resolveVersion($request);

        if ($version === null) {
            return;
        }

        // Add version headers to the response
        $this->headers->addToResponse($event->response, $version, $request);
    }

    /**
     * Resolve the version from multiple sources for maximum compatibility.
     *
     * This method tries multiple approaches to ensure headers are added
     * even in edge cases like cached routes or middleware execution issues.
     */
    private function resolveVersion(Request $request): ?VersionDefinition
    {
        // 1. Try from context (set by middleware)
        if ($this->context->hasVersion()) {
            return $this->context->getVersion();
        }

        // 2. Try from request attributes (also set by middleware)
        $version = $request->attributes->get('api_version_definition');
        if ($version instanceof VersionDefinition) {
            return $version;
        }

        // 3. Check if this looks like an API version request
        // Only try to resolve if the path contains a version pattern
        $path = $request->path();
        if (! preg_match('/\bv\d+\b/i', $path)) {
            return null;
        }

        // 4. Try to resolve directly from request (fallback for edge cases)
        // This handles scenarios where middleware didn't execute or context was lost
        // Only proceed if the version actually exists (don't fall back to other versions)
        $resolved = $this->resolver->resolve($request);

        // Verify the resolved version matches what was requested
        // to avoid adding headers for fallback versions on non-existent version requests
        if ($resolved !== null) {
            $requestedVersion = $this->resolver->getRequestedVersion($request);
            if ($requestedVersion !== null && $resolved->name() === $requestedVersion) {
                return $resolved;
            }
        }

        return null;
    }
}
