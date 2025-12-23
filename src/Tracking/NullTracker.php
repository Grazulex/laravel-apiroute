<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tracking;

use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;

class NullTracker implements VersionTrackerInterface
{
    public function track(
        string $version,
        string $endpoint,
        string $method,
        int $status
    ): void {
        // No-op
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(string $version, int $days = 30): array
    {
        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllStats(int $days = 30): array
    {
        return [];
    }
}
