<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class FallbackRoute
{
    public function __construct(
        private readonly ApiRouteManager $manager
    ) {}

    /**
     * Handle route fallback for versioned APIs.
     *
     * When a route doesn't exist in the requested version,
     * try to find it in a previous version.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only handle 404 responses
        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        // Check if fallback is enabled
        /** @var array<string, mixed> $fallbackConfig */
        $fallbackConfig = config('apiroute.fallback', []);

        if (($fallbackConfig['enabled'] ?? true) === false) {
            return $response;
        }

        // Get current version from request
        $currentVersion = $request->attributes->get('api_version');
        if ($currentVersion === null) {
            return $response;
        }

        // Extract the path without version prefix
        $path = $this->extractPathWithoutVersion($request->path(), $currentVersion);
        if ($path === null) {
            return $response;
        }

        // Try to find the route in a previous version
        $fallbackVersion = $this->findFallbackVersion($currentVersion, $path, $request->method());

        if ($fallbackVersion === null) {
            return $response;
        }

        // Redirect or forward to the fallback version
        return $this->handleFallback($request, $fallbackVersion, $path, $fallbackConfig);
    }

    private function extractPathWithoutVersion(string $fullPath, string $version): ?string
    {
        // Remove API prefix and version from path
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = config('apiroute.strategies.uri', []);
        $prefix = $uriConfig['prefix'] ?? 'api';

        $pattern = '/^' . preg_quote($prefix, '/') . '\/' . preg_quote($version, '/') . '\//';

        if (preg_match($pattern, $fullPath)) {
            return preg_replace($pattern, '', $fullPath);
        }

        // Try without prefix (if prefix is empty)
        $pattern = '/^' . preg_quote($version, '/') . '\//';
        if (preg_match($pattern, $fullPath)) {
            return preg_replace($pattern, '', $fullPath);
        }

        return null;
    }

    private function findFallbackVersion(string $currentVersion, string $path, string $method): ?VersionDefinition
    {
        /** @var array<string, mixed> $fallbackConfig */
        $fallbackConfig = config('apiroute.fallback', []);
        $strategy = $fallbackConfig['strategy'] ?? 'previous';

        if ($strategy === 'latest') {
            // Try the current/latest active version
            $version = $this->manager->currentVersion();
            if ($version !== null && $version->name() !== $currentVersion) {
                if ($this->routeExistsInVersion($version, $path, $method)) {
                    return $version;
                }
            }
        }

        // Default: try previous versions
        return $this->findPreviousVersionWithRoute($currentVersion, $path, $method);
    }

    private function findPreviousVersionWithRoute(string $currentVersion, string $path, string $method): ?VersionDefinition
    {
        // Extract version number
        $currentNumber = (int) preg_replace('/\D/', '', $currentVersion);

        // Try previous versions from highest to lowest
        for ($i = $currentNumber - 1; $i >= 1; $i--) {
            $version = $this->manager->getVersion('v' . $i);

            if ($version === null || ! $version->isUsable()) {
                continue;
            }

            if ($this->routeExistsInVersion($version, $path, $method)) {
                return $version;
            }
        }

        return null;
    }

    private function routeExistsInVersion(VersionDefinition $version, string $path, string $method): bool
    {
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = config('apiroute.strategies.uri', []);
        $prefix = $uriConfig['prefix'] ?? 'api';

        // Build the full path for this version
        $fullPath = trim($prefix . '/' . $version->name() . '/' . $path, '/');

        // Check if route exists
        $routes = Route::getRoutes();

        try {
            $routes->match(
                Request::create('/' . $fullPath, $method)
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function handleFallback(
        Request $request,
        VersionDefinition $fallbackVersion,
        string $path,
        array $config
    ): Response {
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = config('apiroute.strategies.uri', []);
        $prefix = $uriConfig['prefix'] ?? 'api';

        // Build the fallback URL
        $fallbackPath = trim($prefix . '/' . $fallbackVersion->name() . '/' . $path, '/');

        // Preserve query string
        $queryString = $request->getQueryString();
        $fullUrl = '/' . $fallbackPath . ($queryString ? '?' . $queryString : '');

        // Return a redirect response with fallback header
        $response = redirect($fullUrl);

        if (($config['add_header'] ?? true) === true) {
            $response->header('X-API-Version-Fallback', $fallbackVersion->name());
        }

        return $response;
    }
}
