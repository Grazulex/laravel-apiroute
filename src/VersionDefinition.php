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
