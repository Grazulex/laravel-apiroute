<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute;

use Closure;
use Grazulex\ApiRoute\Contracts\VersionResolverInterface;
use Grazulex\ApiRoute\Events\VersionCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ApiRouteManager
{
    /** @var Collection<string, VersionDefinition> */
    private Collection $versions;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config
    ) {
        $this->versions = collect();
    }

    /**
     * Define a new API version.
     *
     * @param  Closure(): void  $routes
     */
    public function version(string $version, Closure $routes): VersionDefinition
    {
        $definition = new VersionDefinition($version, $routes);
        $this->versions->put($version, $definition);

        $this->registerRoutes($definition);

        event(new VersionCreated($definition));

        return $definition;
    }

    /**
     * Get all registered versions.
     *
     * @return Collection<string, VersionDefinition>
     */
    public function versions(): Collection
    {
        return $this->versions;
    }

    /**
     * Get a specific version definition.
     */
    public function getVersion(string $version): ?VersionDefinition
    {
        return $this->versions->get($version);
    }

    /**
     * Get the version marked as current.
     */
    public function currentVersion(): ?VersionDefinition
    {
        return $this->versions->first(fn (VersionDefinition $v) => $v->isActive());
    }

    /**
     * Resolve the API version from a request.
     */
    public function resolveVersion(Request $request): ?string
    {
        $resolver = app(VersionResolverInterface::class);

        $definition = $resolver->resolve($request);

        return $definition?->name();
    }

    /**
     * Check if a version exists.
     */
    public function hasVersion(string $version): bool
    {
        return $this->versions->has($version);
    }

    /**
     * Check if a version is deprecated.
     */
    public function isDeprecated(string $version): bool
    {
        return $this->getVersion($version)?->isDeprecated() ?? false;
    }

    /**
     * Check if a version is sunset.
     */
    public function isSunset(string $version): bool
    {
        return $this->getVersion($version)?->isSunset() ?? false;
    }

    /**
     * Check if a version is active.
     */
    public function isActive(string $version): bool
    {
        return $this->getVersion($version)?->isActive() ?? false;
    }

    /**
     * Register routes for a version definition.
     */
    private function registerRoutes(VersionDefinition $definition): void
    {
        /** @var string $strategy */
        $strategy = $this->config['strategy'] ?? 'uri';

        if ($strategy === 'uri') {
            $this->registerUriRoutes($definition);
        } else {
            $this->registerNonUriRoutes($definition);
        }
    }

    /**
     * Register routes with URI prefix (e.g., /api/v1/...).
     */
    private function registerUriRoutes(VersionDefinition $definition): void
    {
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = $this->config['strategies']['uri'] ?? [];

        $prefix = ($uriConfig['prefix'] ?? 'api') . '/' . $definition->name();

        Route::prefix($prefix)
            ->middleware(['api', 'api.version', 'api.rateLimit'])
            ->group($definition->routes());
    }

    /**
     * Register routes without version prefix (for header/query strategies).
     */
    private function registerNonUriRoutes(VersionDefinition $definition): void
    {
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = $this->config['strategies']['uri'] ?? [];

        $prefix = $uriConfig['prefix'] ?? 'api';

        Route::prefix($prefix)
            ->middleware(['api', 'api.version', 'api.rateLimit'])
            ->group($definition->routes());
    }
}
