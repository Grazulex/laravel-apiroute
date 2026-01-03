<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Support;

use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\Request;

/**
 * Singleton that holds the resolved API version context.
 *
 * This class stores the resolved version information so it can be accessed
 * by listeners and exception handlers to add version headers to all responses,
 * including error responses.
 */
class ApiVersionContext
{
    private ?VersionDefinition $version = null;

    private ?Request $request = null;

    /**
     * Set the resolved version context.
     */
    public function set(VersionDefinition $version, Request $request): void
    {
        $this->version = $version;
        $this->request = $request;
    }

    /**
     * Get the resolved version definition.
     */
    public function getVersion(): ?VersionDefinition
    {
        return $this->version;
    }

    /**
     * Get the original request.
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * Check if a version has been resolved.
     */
    public function hasVersion(): bool
    {
        return $this->version !== null;
    }

    /**
     * Clear the context (useful for testing).
     */
    public function clear(): void
    {
        $this->version = null;
        $this->request = null;
    }
}
