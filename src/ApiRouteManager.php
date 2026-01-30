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

    private bool $configVersionsLoaded = false;

    public function __construct()
    {
        $this->versions = collect();
    }

    /**
     * Boot the manager by loading versions from configuration.
     *
     * This method is called by the service provider on every application boot,
     * ensuring versions are always available (including between tests).
     */
    public function boot(): void
    {
        if ($this->configVersionsLoaded) {
            return;
        }

        $this->loadVersionsFromConfig();
        $this->configVersionsLoaded = true;
    }

    /**
     * Load and register versions defined in configuration.
     *
     * Note: We read config() dynamically here to support runtime config changes
     * (especially important for testing scenarios).
     */
    private function loadVersionsFromConfig(): void
    {
        /** @var array<string, array<string, mixed>> $versions */
        $versions = config('apiroute.versions', []);

        foreach ($versions as $versionName => $versionConfig) {
            if ($this->versions->has($versionName)) {
                continue;
            }

            $definition = VersionDefinition::fromConfig($versionName, $versionConfig);
            $this->versions->put($versionName, $definition);
            $this->registerRoutes($definition);
            event(new VersionCreated($definition));
        }
    }

    /**
     * Reset the manager state.
     *
     * This is useful for testing scenarios where you need to reload versions.
     */
    public function reset(): void
    {
        $this->versions = collect();
        $this->configVersionsLoaded = false;
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
     *
     * Note: We read config() dynamically to support runtime config changes.
     */
    private function registerRoutes(VersionDefinition $definition): void
    {
        /** @var string $strategy */
        $strategy = config('apiroute.strategy', 'uri');

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
        $uriConfig = config('apiroute.strategies.uri', []);

        $prefix = $uriConfig['prefix'] ?? 'api';
        $domain = $uriConfig['domain'] ?? null;

        // Build prefix: include version name
        $fullPrefix = $prefix !== '' ? $prefix . '/' . $definition->name() : $definition->name();

        $routeGroup = Route::prefix($fullPrefix)
            ->middleware($this->getMiddleware($definition));

        // Apply domain if configured (for subdomain-based routing)
        if ($domain !== null && $domain !== '') {
            $routeGroup->domain($domain);
        }

        // Apply route name prefix if defined
        $routeName = $definition->routeName();
        if ($routeName !== null) {
            $routeGroup->name($routeName);
        }

        $routeGroup->group($definition->routes());
    }

    /**
     * Register routes without version prefix (for header/query strategies).
     */
    private function registerNonUriRoutes(VersionDefinition $definition): void
    {
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = config('apiroute.strategies.uri', []);

        $prefix = $uriConfig['prefix'] ?? 'api';
        $domain = $uriConfig['domain'] ?? null;

        $routeGroup = Route::prefix($prefix)
            ->middleware($this->getMiddleware($definition));

        // Apply domain if configured (for subdomain-based routing)
        if ($domain !== null && $domain !== '') {
            $routeGroup->domain($domain);
        }

        // Apply route name prefix if defined
        $routeName = $definition->routeName();
        if ($routeName !== null) {
            $routeGroup->name($routeName);
        }

        $routeGroup->group($definition->routes());
    }

    /**
     * Get the middleware stack for API routes.
     *
     * @return array<string>
     */
    private function getMiddleware(?VersionDefinition $definition = null): array
    {
        $middleware = ['api', 'api.version', 'api.rateLimit'];

        // Add fallback middleware if enabled
        /** @var bool $fallbackEnabled */
        $fallbackEnabled = config('apiroute.fallback.enabled', true);

        if ($fallbackEnabled === true) {
            $middleware[] = 'api.fallback';
        }

        // Add tracking middleware if enabled
        /** @var bool $trackingEnabled */
        $trackingEnabled = config('apiroute.tracking.enabled', false);

        if ($trackingEnabled === true) {
            $middleware[] = 'api.track';
        }

        // Merge with version-specific middleware from config
        if ($definition !== null) {
            $versionMiddleware = $definition->middlewares();
            if (is_string($versionMiddleware)) {
                $versionMiddleware = [$versionMiddleware];
            }
            if (! empty($versionMiddleware)) {
                $middleware = array_merge($middleware, $versionMiddleware);
            }
        }

        return $middleware;
    }
}
