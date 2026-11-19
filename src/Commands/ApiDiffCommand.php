<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Commands;

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * Compares the routes declared by two API versions.
 *
 * Routes are compared on their HTTP method and their URI relative to the
 * version (e.g. `GET users/{id}`), so the comparison does not depend on the
 * detection strategy, the URI prefix or the configured domains. Parameter
 * names are ignored: `users/{id}` and `users/{user}` are the same endpoint.
 */
class ApiDiffCommand extends Command
{
    protected $signature = 'api:diff
                            {from : The version to compare from (e.g. v1)}
                            {to : The version to compare to (e.g. v2)}
                            {--json : Output as JSON}
                            {--fail-on-removed : Exit with a failure code when routes were removed}
                            {--fail-on-changes : Exit with a failure code when any difference is found}';

    protected $description = 'List the routes added, removed or deprecated between two API versions';

    public function handle(ApiRouteManager $manager, EndpointLifecycleResolver $resolver): int
    {
        $fromName = (string) $this->argument('from');
        $toName = (string) $this->argument('to');

        $from = $manager->getVersion($fromName);
        if (! $from instanceof VersionDefinition) {
            $this->error("Version '{$fromName}' not found.");

            return self::FAILURE;
        }

        $to = $manager->getVersion($toName);
        if (! $to instanceof VersionDefinition) {
            $this->error("Version '{$toName}' not found.");

            return self::FAILURE;
        }

        $fromRoutes = $this->collectRoutes($from, $resolver);
        $toRoutes = $this->collectRoutes($to, $resolver);

        $added = array_values(array_diff_key($toRoutes, $fromRoutes));
        $removed = array_values(array_diff_key($fromRoutes, $toRoutes));

        $deprecated = [];
        $common = array_intersect_key($toRoutes, $fromRoutes);
        foreach ($common as $key => $route) {
            if ($route['lifecycle'] instanceof EndpointLifecycle && ! $fromRoutes[$key]['lifecycle'] instanceof EndpointLifecycle) {
                $deprecated[] = $route;
            }
        }

        $payload = [
            'from' => $fromName,
            'to' => $toName,
            'added' => array_map($this->formatRoute(...), $added),
            'removed' => array_map($this->formatRoute(...), $removed),
            'deprecated' => array_map($this->formatDeprecatedRoute(...), $deprecated),
            'summary' => [
                'added' => count($added),
                'removed' => count($removed),
                'deprecated' => count($deprecated),
                'unchanged' => count($common) - count($deprecated),
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        } else {
            $this->renderText($payload);
        }

        return $this->exitCode($payload['summary']);
    }

    /**
     * Registers the routes of a version on an isolated router, so they can be
     * inspected without the version prefix and without touching the
     * application's route collection.
     *
     * @return array<string, array{method: string, uri: string, name: string|null, lifecycle: EndpointLifecycle|null}>
     */
    private function collectRoutes(VersionDefinition $version, EndpointLifecycleResolver $resolver): array
    {
        /** @var Dispatcher $events */
        $events = $this->laravel->make('events');
        $container = $this->laravel instanceof Container ? $this->laravel : Container::getInstance();
        $router = new Router($events, $container);

        $original = Route::getFacadeRoot();
        Route::swap($router);

        try {
            $router->group([], $version->routes());
        } finally {
            Route::swap($original);
        }

        $routes = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $lifecycle = $resolver->forRoute($route);
            $uri = $route->uri();

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $routes[$method . ' ' . $this->normalizeUri($uri)] = [
                    'method' => $method,
                    'uri' => $uri,
                    'name' => $route->getName(),
                    'lifecycle' => $lifecycle,
                ];
            }
        }

        uasort($routes, fn (array $a, array $b): int => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]);

        return $routes;
    }

    /**
     * Replaces parameter names so that `users/{id}` matches `users/{user}`.
     */
    private function normalizeUri(string $uri): string
    {
        return (string) preg_replace(['/\{[^}?]+\?\}/', '/\{[^}?]+\}/'], ['{?}', '{}'], trim($uri, '/'));
    }

    /**
     * @param  array{method: string, uri: string, name: string|null, lifecycle: EndpointLifecycle|null}  $route
     *
     * @return array{method: string, uri: string, name: string|null}
     */
    private function formatRoute(array $route): array
    {
        return [
            'method' => $route['method'],
            'uri' => $route['uri'],
            'name' => $route['name'],
        ];
    }

    /**
     * @param  array{method: string, uri: string, name: string|null, lifecycle: EndpointLifecycle|null}  $route
     *
     * @return array{method: string, uri: string, name: string|null, since: string|null, sunset: string|null, successor: string|null}
     */
    private function formatDeprecatedRoute(array $route): array
    {
        return [
            ...$this->formatRoute($route),
            'since' => $route['lifecycle']?->deprecatedAt?->format('Y-m-d'),
            'sunset' => $route['lifecycle']?->sunsetAt?->format('Y-m-d'),
            'successor' => $route['lifecycle']?->successor,
        ];
    }

    /**
     * @param  array{from: string, to: string, added: list<array{method: string, uri: string, name: string|null}>, removed: list<array{method: string, uri: string, name: string|null}>, deprecated: list<array{method: string, uri: string, name: string|null, since: string|null, sunset: string|null, successor: string|null}>, summary: array{added: int, removed: int, deprecated: int, unchanged: int}}  $payload
     */
    private function renderText(array $payload): void
    {
        $summary = $payload['summary'];

        $this->info("Comparing routes of {$payload['from']} to {$payload['to']}");

        if ($summary['added'] + $summary['removed'] + $summary['deprecated'] === 0) {
            $this->newLine();
            $this->line("No differences between {$payload['from']} and {$payload['to']}.");

            return;
        }

        $simple = fn (array $row): array => [$row['method'], $row['uri'], $row['name'] ?? '-'];

        if ($payload['added'] !== []) {
            $this->newLine();
            $this->line('<fg=green>Added routes</>');
            $this->table(['Method', 'URI', 'Name'], array_map($simple, $payload['added']));
        }

        if ($payload['removed'] !== []) {
            $this->newLine();
            $this->line('<fg=red>Removed routes</>');
            $this->table(['Method', 'URI', 'Name'], array_map($simple, $payload['removed']));
        }

        if ($payload['deprecated'] !== []) {
            $this->newLine();
            $this->line('<fg=yellow>Deprecated routes</>');
            $this->table(
                ['Method', 'URI', 'Since', 'Sunset', 'Successor'],
                array_map(fn (array $row): array => [
                    $row['method'],
                    $row['uri'],
                    $row['since'] ?? '-',
                    $row['sunset'] ?? '-',
                    $row['successor'] ?? '-',
                ], $payload['deprecated']),
            );
        }

        $this->newLine();
        $this->line(sprintf(
            'Summary: %d added, %d removed, %d deprecated, %d unchanged',
            $summary['added'],
            $summary['removed'],
            $summary['deprecated'],
            $summary['unchanged'],
        ));
    }

    /**
     * @param  array{added: int, removed: int, deprecated: int, unchanged: int}  $summary
     */
    private function exitCode(array $summary): int
    {
        if ($this->option('fail-on-removed') && $summary['removed'] > 0) {
            return self::FAILURE;
        }

        if ($this->option('fail-on-changes') && $summary['added'] + $summary['removed'] + $summary['deprecated'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
