<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\OpenApi;

use Carbon\Carbon;
use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route as RouteFacade;
use stdClass;

/**
 * Builds an OpenAPI document describing the paths and the lifecycle of one
 * API version.
 *
 * Paths are relative to the version (`/users/{id}`); the version prefix lives
 * in the server URL. Request and response schemas are not generated.
 */
final readonly class OpenApiGenerator
{
    public const OPENAPI_VERSION = '3.0.3';

    private const METHODS = ['GET', 'PUT', 'POST', 'DELETE', 'OPTIONS', 'HEAD', 'PATCH', 'TRACE'];

    public function __construct(
        private Application $app,
        private EndpointLifecycleResolver $resolver,
    ) {}

    /**
     * @param  list<string>  $servers  Server URLs replacing the ones derived from the configuration
     *
     * @return array<string, mixed>
     */
    public function generate(VersionDefinition $version, ?string $title = null, array $servers = []): array
    {
        $document = [
            'openapi' => self::OPENAPI_VERSION,
            'info' => $this->info($version, $title),
            'servers' => $servers === []
                ? $this->servers($version)
                : array_map(fn (string $url): array => ['url' => $url], $servers),
        ];

        $documentation = $version->documentationUrl();
        if ($documentation !== null && $documentation !== '') {
            $document['externalDocs'] = ['url' => $documentation];
        }

        $paths = $this->paths($version);
        $document['paths'] = $paths === [] ? new stdClass : $paths;

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function info(VersionDefinition $version, ?string $title): array
    {
        if ($title === null || $title === '') {
            $appName = config('app.name');
            $title = (is_string($appName) && $appName !== '' ? $appName : 'Laravel') . ' API';
        }

        $info = [
            'title' => $title,
            'version' => $version->name(),
        ];

        $description = $this->description($version);
        if ($description !== '') {
            $info['description'] = $description;
        }

        $info['x-api-status'] = $version->isSunset() ? 'sunset' : $version->status()->value;

        if ($version->deprecationDate() instanceof Carbon) {
            $info['x-deprecated-at'] = $this->formatDate($version->deprecationDate());
        }

        if ($version->sunsetDate() instanceof Carbon) {
            $info['x-sunset'] = $this->formatDate($version->sunsetDate());
        }

        if ($version->successor() !== null && $version->successor() !== '') {
            $info['x-successor'] = $version->successor();
        }

        return $info;
    }

    private function description(VersionDefinition $version): string
    {
        $sentences = [];

        if ($version->isDeprecated() || $version->isSunset()) {
            $sentences[] = $version->deprecationDate() instanceof Carbon
                ? 'This version is deprecated since ' . $this->formatDate($version->deprecationDate()) . '.'
                : 'This version is deprecated.';
        }

        if ($version->sunsetDate() instanceof Carbon) {
            $sentences[] = ($version->isSunset() ? 'It was sunset on ' : 'It will be sunset on ')
                . $this->formatDate($version->sunsetDate()) . '.';
        }

        if ($version->successor() !== null && $version->successor() !== '') {
            $sentences[] = "Use version {$version->successor()} instead.";
        }

        if ($this->strategy() === 'accept') {
            $vendor = config('apiroute.strategies.accept.vendor', 'api');
            $vendor = is_string($vendor) && $vendor !== '' ? $vendor : 'api';

            $sentences[] = "Select this version with the header `Accept: application/vnd.{$vendor}.{$version->name()}+json`.";
        }

        return implode(' ', $sentences);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function servers(VersionDefinition $version): array
    {
        /** @var array<string, mixed> $uriConfig */
        $uriConfig = config('apiroute.strategies.uri', []);

        $prefix = $uriConfig['prefix'] ?? 'api';
        $segments = [];

        if (is_string($prefix) && trim($prefix, '/') !== '') {
            $segments[] = trim($prefix, '/');
        }

        if ($this->strategy() === 'uri') {
            $segments[] = $version->name();
        }

        $path = $segments === [] ? '' : '/' . implode('/', $segments);

        $appUrl = config('app.url');
        $base = rtrim(is_string($appUrl) && $appUrl !== '' ? $appUrl : 'http://localhost', '/');

        $configured = $uriConfig['domain'] ?? null;
        $domains = [];
        foreach (is_array($configured) ? $configured : [$configured] as $domain) {
            if (is_string($domain) && $domain !== '') {
                $domains[] = $domain;
            }
        }

        if ($domains === []) {
            return [['url' => $base . $path]];
        }

        $scheme = parse_url($base, PHP_URL_SCHEME);
        $scheme = is_string($scheme) && $scheme !== '' ? $scheme : 'https';
        $port = parse_url($base, PHP_URL_PORT);
        $port = is_int($port) ? ':' . $port : '';

        $servers = [];

        foreach ($domains as $domain) {
            $server = ['url' => "{$scheme}://{$domain}{$port}{$path}"];

            // A domain such as `{tenant}.example.com` becomes a server variable.
            if (preg_match_all('/\{([^}]+)\}/', $domain, $matches) > 0) {
                $variables = [];
                foreach ($matches[1] as $variable) {
                    $variables[$variable] = ['default' => $variable];
                }
                $server['variables'] = $variables;
            }

            $servers[] = $server;
        }

        return $servers;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function paths(VersionDefinition $version): array
    {
        $paths = [];
        $operationIds = [];

        foreach ($this->routesOf($version) as $route) {
            $lifecycle = $this->resolver->forRoute($route);
            $methods = array_values(array_intersect($route->methods(), self::METHODS));

            // HEAD is implied by GET in Laravel; only document it on its own.
            if (in_array('GET', $methods, true)) {
                $methods = array_values(array_diff($methods, ['HEAD']));
            }

            foreach ($this->pathVariants($route->uri()) as $path) {
                foreach ($methods as $method) {
                    $operationId = $this->operationId($route, $method, $path, count($methods) > 1, $operationIds);

                    $paths[$path][strtolower($method)] = $this->operation($version, $route, $path, $operationId, $lifecycle);
                }
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * Registers the routes of the version on an isolated router, so they are
     * read without the version prefix and without touching the application's
     * route collection.
     *
     * @return list<Route>
     */
    private function routesOf(VersionDefinition $version): array
    {
        /** @var Dispatcher $events */
        $events = $this->app->make('events');
        $container = $this->app instanceof Container ? $this->app : Container::getInstance();
        $router = new Router($events, $container);

        $original = RouteFacade::getFacadeRoot();
        RouteFacade::swap($router);

        try {
            $router->group([], $version->routes());
        } finally {
            RouteFacade::swap($original);
        }

        return array_values($router->getRoutes()->getRoutes());
    }

    /**
     * OpenAPI has no optional path parameter: a route ending with optional
     * parameters is documented once with them and once without.
     *
     * @return list<string>
     */
    private function pathVariants(string $uri): array
    {
        $uri = trim($uri, '/');
        $variants = [$this->toPath($uri)];

        while (preg_match('#^(.*?)/?\{[^/{}]+\?\}$#', $uri, $matches) === 1) {
            $uri = $matches[1];
            $variants[] = $this->toPath($uri);
        }

        return array_values(array_unique($variants));
    }

    private function toPath(string $uri): string
    {
        return '/' . (string) preg_replace('/\{([^}:?]+)(?::[^}?]*)?\??\}/', '{$1}', $uri);
    }

    /**
     * @param  array<string, true>  $used
     */
    private function operationId(Route $route, string $method, string $path, bool $multipleMethods, array &$used): string
    {
        $name = $route->getName();

        if ($name !== null && $name !== '') {
            $base = $multipleMethods ? $name . '.' . strtolower($method) : $name;
        } else {
            $words = preg_split('/[^A-Za-z0-9]+/', $path, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $base = strtolower($method) . implode('', array_map(ucfirst(...), $words));
        }

        $operationId = $base;
        $suffix = 2;

        while (isset($used[$operationId])) {
            $operationId = $base . '_' . $suffix++;
        }

        $used[$operationId] = true;

        return $operationId;
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(VersionDefinition $version, Route $route, string $path, string $operationId, ?EndpointLifecycle $lifecycle): array
    {
        $operation = [];

        $tag = explode('/', ltrim($path, '/'))[0];
        if ($tag !== '' && ! str_starts_with($tag, '{')) {
            $operation['tags'] = [$tag];
        }

        $operation['operationId'] = $operationId;

        if ($lifecycle?->reason !== null && $lifecycle->reason !== '') {
            $operation['description'] = $lifecycle->reason;
        }

        if ($lifecycle instanceof EndpointLifecycle || $version->isDeprecated() || $version->isSunset()) {
            $operation['deprecated'] = true;
        }

        $deprecatedAt = $lifecycle->deprecatedAt ?? $version->deprecationDate();
        if ($deprecatedAt instanceof Carbon) {
            $operation['x-deprecated-at'] = $this->formatDate($deprecatedAt);
        }

        $sunsetAt = $lifecycle->sunsetAt ?? $version->sunsetDate();
        if ($sunsetAt instanceof Carbon) {
            $operation['x-sunset'] = $this->formatDate($sunsetAt);
        }

        if ($lifecycle?->successor !== null && $lifecycle->successor !== '') {
            $operation['x-successor'] = $lifecycle->successor;
        }

        if ($lifecycle?->docs !== null && $lifecycle->docs !== '') {
            $operation['externalDocs'] = ['url' => $lifecycle->docs];
        }

        $parameters = $this->parameters($version, $route, $path);
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        $operation['responses'] = ['default' => ['description' => 'Response.']];

        return $operation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parameters(VersionDefinition $version, Route $route, string $path): array
    {
        $parameters = [];

        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        foreach (array_unique($matches[1]) as $name) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => $this->parameterSchema($route->wheres[$name] ?? null),
            ];
        }

        $versionParameter = $this->versionParameter($version);
        if ($versionParameter !== null) {
            $parameters[] = $versionParameter;
        }

        return $parameters;
    }

    /**
     * @return array<string, string>
     */
    private function parameterSchema(mixed $pattern): array
    {
        if (! is_string($pattern) || $pattern === '') {
            return ['type' => 'string'];
        }

        if (in_array($pattern, ['[0-9]+', '\d+'], true)) {
            return ['type' => 'integer'];
        }

        return ['type' => 'string', 'pattern' => '^(?:' . $pattern . ')$'];
    }

    /**
     * The header or query parameter selecting the version, for the strategies
     * where it is not part of the URL.
     *
     * @return array<string, mixed>|null
     */
    private function versionParameter(VersionDefinition $version): ?array
    {
        $strategy = $this->strategy();

        if ($strategy === 'header') {
            $name = config('apiroute.strategies.header.name', 'X-API-Version');
            $name = is_string($name) && $name !== '' ? $name : 'X-API-Version';
        } elseif ($strategy === 'query') {
            $name = config('apiroute.strategies.query.parameter', 'api_version');
            $name = is_string($name) && $name !== '' ? $name : 'api_version';
        } else {
            return null;
        }

        return [
            'name' => $name,
            'in' => $strategy,
            'required' => false,
            'description' => "Selects the API version. Send `{$version->name()}` to use this version.",
            'schema' => ['type' => 'string'],
            'example' => $version->name(),
        ];
    }

    private function strategy(): string
    {
        $strategy = config('apiroute.strategy', 'uri');

        return is_string($strategy) ? $strategy : 'uri';
    }

    private function formatDate(Carbon $date): string
    {
        return $date->format('H:i:s') === '00:00:00' ? $date->format('Y-m-d') : $date->toIso8601String();
    }
}
