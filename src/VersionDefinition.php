<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute;

use Carbon\Carbon;
use Closure;
use Grazulex\ApiRoute\Support\VersionStatus;

class VersionDefinition
{
    private VersionStatus $status = VersionStatus::Active;

    private ?Carbon $deprecationDate = null;

    private ?Carbon $sunsetDate = null;

    private ?string $successor = null;

    private ?string $documentationUrl = null;

    private ?int $rateLimit = null;

    /** @var array<string>|string */
    private array|string $middlewares = [];

    private ?string $routeName = null;

    /**
     * @param  Closure(): void  $routes
     */
    public function __construct(
        private readonly string $name,
        private readonly Closure $routes
    ) {}

    /**
     * Create a VersionDefinition from configuration array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        $routeFile = $config['routes'] ?? null;

        $routes = function () use ($routeFile): void {
            if ($routeFile !== null && file_exists($routeFile)) {
                require $routeFile;
            }
        };

        $definition = new self($name, $routes);

        // Apply middleware if defined
        if (isset($config['middleware'])) {
            $definition->middleware($config['middleware']);
        }

        // Apply status
        $status = $config['status'] ?? 'active';
        match ($status) {
            'active' => $definition->current(),
            'beta' => $definition->beta(),
            'deprecated' => $definition->deprecated($config['deprecated_at'] ?? now()),
            'sunset' => $definition->status = VersionStatus::Sunset,
            default => $definition->current(),
        };

        // Apply dates if set (using array_key_exists to handle null values properly)
        if (array_key_exists('deprecated_at', $config) && $config['deprecated_at'] !== null) {
            /** @var string $deprecatedAt */
            $deprecatedAt = $config['deprecated_at'];
            $definition->deprecationDate = Carbon::parse($deprecatedAt);
            if ($definition->status === VersionStatus::Active) {
                $definition->status = VersionStatus::Deprecated;
            }
        }

        if (array_key_exists('sunset_at', $config) && $config['sunset_at'] !== null) {
            /** @var string $sunsetAt */
            $sunsetAt = $config['sunset_at'];
            $definition->sunset($sunsetAt);
        }

        // Apply successor if set
        if (isset($config['successor'])) {
            $definition->setSuccessor($config['successor']);
        }

        // Apply documentation URL if set
        if (isset($config['documentation'])) {
            $definition->documentation($config['documentation']);
        }

        // Apply rate limit if set
        if (isset($config['rate_limit'])) {
            $definition->rateLimit($config['rate_limit']);
        }

        return $definition;
    }

    /**
     * Magic getter to allow property-style access for Collection::pluck().
     *
     * @return mixed
     */
    public function __get(string $property)
    {
        return match ($property) {
            'name' => $this->name,
            'status' => $this->status,
            'deprecationDate' => $this->deprecationDate,
            'sunsetDate' => $this->sunsetDate,
            'successor' => $this->successor,
            'documentationUrl' => $this->documentationUrl,
            'rateLimit' => $this->rateLimit,
            default => null,
        };
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return Closure(): void
     */
    public function routes(): Closure
    {
        return $this->routes;
    }

    public function status(): VersionStatus
    {
        return $this->status;
    }

    public function current(): self
    {
        $this->status = VersionStatus::Active;

        return $this;
    }

    public function beta(): self
    {
        $this->status = VersionStatus::Beta;

        return $this;
    }

    public function deprecated(string|Carbon $date): self
    {
        $this->status = VersionStatus::Deprecated;
        $this->deprecationDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this;
    }

    public function sunset(string|Carbon $date): self
    {
        $this->sunsetDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        if ($this->sunsetDate->isPast()) {
            $this->status = VersionStatus::Sunset;
        }

        return $this;
    }

    public function setSuccessor(string $version): self
    {
        $this->successor = $version;

        return $this;
    }

    public function documentation(string $url): self
    {
        $this->documentationUrl = $url;

        return $this;
    }

    public function rateLimit(int $requests): self
    {
        $this->rateLimit = $requests;

        return $this;
    }

    /**
     * @param  array<string>|string  $middleware
     */
    public function middleware(array|string $middleware): self
    {
        $this->middlewares = $middleware;

        return $this;
    }

    public function name_(string $name): self
    {
        $this->routeName = $name;

        return $this;
    }

    public function deprecationDate(): ?Carbon
    {
        return $this->deprecationDate;
    }

    public function sunsetDate(): ?Carbon
    {
        return $this->sunsetDate;
    }

    public function successor(): ?string
    {
        return $this->successor;
    }

    public function documentationUrl(): ?string
    {
        return $this->documentationUrl;
    }

    public function rateLimit_(): ?int
    {
        return $this->rateLimit;
    }

    /**
     * @return array<string>|string
     */
    public function middlewares(): array|string
    {
        return $this->middlewares;
    }

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    public function isActive(): bool
    {
        return $this->status === VersionStatus::Active;
    }

    public function isBeta(): bool
    {
        return $this->status === VersionStatus::Beta;
    }

    public function isDeprecated(): bool
    {
        return $this->status === VersionStatus::Deprecated;
    }

    public function isSunset(): bool
    {
        if ($this->status === VersionStatus::Sunset) {
            return true;
        }

        if ($this->sunsetDate !== null && $this->sunsetDate->isPast()) {
            return true;
        }

        return false;
    }

    public function isUsable(): bool
    {
        return ! $this->isSunset();
    }
}
