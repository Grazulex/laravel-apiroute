<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute;

use Grazulex\ApiRoute\Commands\ApiDeprecateCommand;
use Grazulex\ApiRoute\Commands\ApiStatsCommand;
use Grazulex\ApiRoute\Commands\ApiStatusCommand;
use Grazulex\ApiRoute\Commands\ApiSunsetCommand;
use Grazulex\ApiRoute\Commands\ApiVersionCommand;
use Grazulex\ApiRoute\Contracts\VersionResolverInterface;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Grazulex\ApiRoute\Http\Headers\VersionHeaders;
use Grazulex\ApiRoute\Listeners\AddVersionHeadersToResponse;
use Grazulex\ApiRoute\Middleware\FallbackRoute;
use Grazulex\ApiRoute\Middleware\RateLimitApiVersion;
use Grazulex\ApiRoute\Middleware\ResolveApiVersion;
use Grazulex\ApiRoute\Middleware\TrackApiUsage;
use Grazulex\ApiRoute\Support\ApiVersionContext;
use Grazulex\ApiRoute\Tracking\DatabaseTracker;
use Grazulex\ApiRoute\Tracking\NullTracker;
use Grazulex\ApiRoute\Tracking\RedisTracker;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ApiRouteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/apiroute.php', 'apiroute');

        $this->app->singleton(ApiRouteManager::class);

        $this->app->singleton(VersionResolverInterface::class, function ($app): VersionResolver {
            return new VersionResolver(
                $app->make(ApiRouteManager::class)
            );
        });

        $this->app->singleton(VersionHeaders::class);

        $this->app->singleton(ApiVersionContext::class);

        $this->app->singleton(VersionTrackerInterface::class, function ($app): VersionTrackerInterface {
            /** @var array<string, mixed> $trackingConfig */
            $trackingConfig = $app['config']['apiroute.tracking'] ?? [];

            if (($trackingConfig['enabled'] ?? false) === false) {
                return new NullTracker;
            }

            $driver = $trackingConfig['driver'] ?? 'null';

            return match ($driver) {
                'database' => new DatabaseTracker,
                'redis' => new RedisTracker,
                default => new NullTracker,
            };
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/apiroute.php' => config_path('apiroute.php'),
        ], 'apiroute-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'apiroute-migrations');

        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/apiroute'),
        ], 'apiroute-stubs');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiStatusCommand::class,
                ApiVersionCommand::class,
                ApiDeprecateCommand::class,
                ApiSunsetCommand::class,
                ApiStatsCommand::class,
            ]);
        }

        $this->registerMiddleware();
        $this->registerMacros();
        $this->registerEventListeners();

        // Boot the API route manager to load versions from configuration
        // This is called on every application boot, ensuring versions
        // are available even between tests
        $this->app->make(ApiRouteManager::class)->boot();
    }

    private function registerEventListeners(): void
    {
        // Register listener to add version headers to ALL responses (including errors)
        Event::listen(RequestHandled::class, AddVersionHeadersToResponse::class);
    }

    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('api.version', ResolveApiVersion::class);
        $router->aliasMiddleware('api.rateLimit', RateLimitApiVersion::class);
        $router->aliasMiddleware('api.track', TrackApiUsage::class);
        $router->aliasMiddleware('api.fallback', FallbackRoute::class);
    }

    private function registerMacros(): void
    {
        Request::macro('apiVersion', function (): ?string {
            /** @var Request $this */
            return $this->attributes->get('api_version');
        });

        Request::macro('apiVersionDefinition', function (): ?VersionDefinition {
            /** @var Request $this */
            return $this->attributes->get('api_version_definition');
        });

        Request::macro('apiVersionStatus', function (): ?Support\VersionStatus {
            /** @var Request $this */
            $definition = $this->attributes->get('api_version_definition');

            return $definition?->status();
        });

        Request::macro('isDeprecatedVersion', function (): bool {
            /** @var Request $this */
            $definition = $this->attributes->get('api_version_definition');

            return $definition?->isDeprecated() ?? false;
        });
    }
}
