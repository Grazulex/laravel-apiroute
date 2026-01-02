<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute;

use Grazulex\ApiRoute\Contracts\VersionResolverInterface;
use Grazulex\ApiRoute\Support\DetectionStrategy;
use Illuminate\Http\Request;

class VersionResolver implements VersionResolverInterface
{
    public function __construct(
        private readonly ApiRouteManager $manager
    ) {}

    public function resolve(Request $request): ?VersionDefinition
    {
        $versionString = $this->getRequestedVersion($request);

        if ($versionString === null) {
            return $this->getDefaultVersion();
        }

        $version = $this->manager->getVersion($versionString);

        if ($version !== null) {
            return $version;
        }

        // Try with 'v' prefix if not found
        $version = $this->manager->getVersion('v' . $versionString);

        if ($version !== null) {
            return $version;
        }

        // Fallback handling
        return $this->handleFallback($versionString);
    }

    public function getRequestedVersion(Request $request): ?string
    {
        /** @var string $strategyValue */
        $strategyValue = config('apiroute.strategy', 'uri');
        $strategy = DetectionStrategy::from($strategyValue);

        return match ($strategy) {
            DetectionStrategy::Uri => $this->resolveFromUri($request),
            DetectionStrategy::Header => $this->resolveFromHeader($request),
            DetectionStrategy::Query => $this->resolveFromQuery($request),
            DetectionStrategy::Accept => $this->resolveFromAccept($request),
        };
    }

    private function resolveFromUri(Request $request): ?string
    {
        // Extract version from path (e.g., /api/v1/users -> v1)
        $path = $request->path();
        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if (preg_match('/^v\d+$/i', $segment)) {
                return $segment;
            }
        }

        return null;
    }

    private function resolveFromHeader(Request $request): ?string
    {
        /** @var string $headerName */
        $headerName = config('apiroute.strategies.header.name', 'X-API-Version');

        return $request->header($headerName);
    }

    private function resolveFromQuery(Request $request): ?string
    {
        /** @var string $paramName */
        $paramName = config('apiroute.strategies.query.parameter', 'api_version');

        /** @var string|null */
        return $request->query($paramName);
    }

    private function resolveFromAccept(Request $request): ?string
    {
        /** @var string $vendor */
        $vendor = config('apiroute.strategies.accept.vendor', 'api');

        $accept = $request->header('Accept', '');

        // Match pattern like: application/vnd.api.v2+json
        $pattern = '/application\/vnd\.' . preg_quote($vendor, '/') . '\.v(\d+)\+json/i';

        if (preg_match($pattern, $accept, $matches)) {
            return 'v' . $matches[1];
        }

        return null;
    }

    private function getDefaultVersion(): ?VersionDefinition
    {
        /** @var string $default */
        $default = config('apiroute.default_version', 'latest');

        if ($default === 'latest') {
            return $this->manager->versions()
                ->filter(fn (VersionDefinition $v) => $v->isActive())
                ->last();
        }

        return $this->manager->getVersion($default);
    }

    private function handleFallback(string $requestedVersion): ?VersionDefinition
    {
        /** @var bool $fallbackEnabled */
        $fallbackEnabled = config('apiroute.fallback.enabled', true);

        if ($fallbackEnabled === false) {
            return null;
        }

        /** @var string $strategy */
        $strategy = config('apiroute.fallback.strategy', 'previous');

        return match ($strategy) {
            'previous' => $this->getPreviousVersion($requestedVersion),
            'latest' => $this->manager->currentVersion(),
            default => null,
        };
    }

    private function getPreviousVersion(string $requestedVersion): ?VersionDefinition
    {
        // Extract version number
        $versionNumber = (int) preg_replace('/\D/', '', $requestedVersion);

        // Try to find the previous version
        for ($i = $versionNumber - 1; $i >= 1; $i--) {
            $version = $this->manager->getVersion('v' . $i);
            if ($version !== null && $version->isUsable()) {
                return $version;
            }
        }

        return null;
    }
}
