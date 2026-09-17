<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Support;

use Grazulex\ApiRoute\Attributes\Deprecated;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route as Router;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Finds the deprecation metadata of a route: the `deprecated()` macro first,
 * then #[Deprecated] attributes on the controller class and action.
 */
final class EndpointLifecycleResolver
{
    public const ACTION_KEY = 'apiroute.deprecated';

    /** @var array<string, EndpointLifecycle|null> keyed by action name */
    private static array $cache = [];

    public function __construct(private readonly Application $app) {}

    public function forRoute(Route $route): ?EndpointLifecycle
    {
        $macro = $route->getAction(self::ACTION_KEY);
        if (is_array($macro)) {
            return EndpointLifecycle::fromArray($macro);
        }

        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return null;
        }

        if (array_key_exists($action, self::$cache)) {
            return self::$cache[$action];
        }

        return self::$cache[$action] = $this->fromAttributes($action);
    }

    public function forRequest(Request $request): ?EndpointLifecycle
    {
        $route = $request->route();

        return $route instanceof Route ? $this->forRoute($route) : null;
    }

    public function resolveSuccessorUrl(EndpointLifecycle $lifecycle): ?string
    {
        $successor = $lifecycle->successor;

        if ($successor === null || $successor === '') {
            return null;
        }

        if (str_starts_with($successor, '/')) {
            return url($successor);
        }

        if (! str_contains($successor, '://')) {
            $routes = Router::getFacadeRoot()->getRoutes();

            // Route::name() only sets the route's "as" action; the collection's
            // name look-up table is otherwise refreshed lazily (e.g. on the
            // first url()/route() call or when matching a real HTTP request).
            // Refresh once, only on a miss, so a route named fluently earlier
            // in the same request/test is still found without paying an
            // O(routes) rebuild on every resolution.
            if (! $routes->hasNamedRoute($successor)) {
                $routes->refreshNameLookups();
            }

            if ($routes->hasNamedRoute($successor)) {
                return route($successor);
            }

            if ($this->looksLikeRouteName($successor)) {
                if ($this->app->environment(['local', 'testing'])) {
                    throw new InvalidArgumentException("[apiroute] unknown successor route [{$successor}].");
                }

                Log::warning('[apiroute] unknown successor route', ['successor' => $successor]);

                return null;
            }
        }

        return $successor;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private function fromAttributes(string $action): ?EndpointLifecycle
    {
        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $lifecycle = $this->attributeOf($reflection);

        if ($reflection->hasMethod($method)) {
            $methodLifecycle = $this->attributeOf($reflection->getMethod($method));
            $lifecycle = $lifecycle instanceof EndpointLifecycle && $methodLifecycle instanceof EndpointLifecycle
                ? $lifecycle->merge($methodLifecycle)
                : ($methodLifecycle ?? $lifecycle);
        }

        return $lifecycle;
    }

    private function attributeOf(ReflectionClass|ReflectionMethod $target): ?EndpointLifecycle
    {
        $attributes = $target->getAttributes(Deprecated::class);

        return $attributes === [] ? null : EndpointLifecycle::fromAttribute($attributes[0]->newInstance());
    }

    private function looksLikeRouteName(string $value): bool
    {
        return ! str_contains($value, '/') && ! str_contains($value, '://') && str_contains($value, '.');
    }
}
