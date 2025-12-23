<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Contracts;

interface VersionTrackerInterface
{
    /**
     * Track an API request.
     */
    public function track(
        string $version,
        string $endpoint,
        string $method,
        int $status
    ): void;

    /**
     * Get usage statistics for a version.
     *
     * @return array<string, mixed>
     */
    public function getStats(string $version, int $days = 30): array;

    /**
     * Get usage statistics for all versions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllStats(int $days = 30): array;
}
