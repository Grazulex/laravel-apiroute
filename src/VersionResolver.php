<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute;

use Grazulex\ApiRoute\Contracts\VersionResolverInterface;
use Grazulex\ApiRoute\Support\DetectionStrategy;
use Illuminate\Http\Request;

class VersionResolver implements VersionResolverInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly ApiRouteManager $manager,
        private readonly array $config
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
        $strategy = DetectionStrategy::from($this->config['strategy'] ?? 'uri');

        return match ($strategy) {
            DetectionStrategy::Uri => $this->resolveFromUri($request),
            DetectionStrategy::Header => $this->resolveFromHeader($request),
            DetectionStrategy::Query => $this->resolveFromQuery($request),
            DetectionStrategy::Accept => $this->resolveFromAccept($request),
        };
    }

    private function resolveFromUri(Request $request): ?string
    {
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = $this->config['strategies']['uri'] ?? [];
        $pattern = $uriConfig['pattern'] ?? 'v{version}';

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
        /** @var array<string, mixed> $headerConfig */
        $headerConfig = $this->config['strategies']['header'] ?? [];
        $headerName = $headerConfig['name'] ?? 'X-API-Version';

        return $request->header($headerName);
    }

    private function resolveFromQuery(Request $request): ?string
    {
        /** @var array<string, mixed> $queryConfig */
        $queryConfig = $this->config['strategies']['query'] ?? [];
        $paramName = $queryConfig['parameter'] ?? 'api_version';

        /** @var string|null */
        return $request->query($paramName);
    }

    private function resolveFromAccept(Request $request): ?string
    {
        /** @var array<string, mixed> $acceptConfig */
        $acceptConfig = $this->config['strategies']['accept'] ?? [];
        $vendor = $acceptConfig['vendor'] ?? 'api';

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
        $default = $this->config['default_version'] ?? 'latest';

        if ($default === 'latest') {
            return $this->manager->versions()
                ->filter(fn (VersionDefinition $v) => $v->isActive())
                ->last();
        }

        return $this->manager->getVersion($default);
    }

    private function handleFallback(string $requestedVersion): ?VersionDefinition
    {
        /** @var array<string, mixed> $fallbackConfig */
        $fallbackConfig = $this->config['fallback'] ?? [];

        if (($fallbackConfig['enabled'] ?? true) === false) {
            return null;
        }

        $strategy = $fallbackConfig['strategy'] ?? 'previous';

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
