<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tracking;

use Grazulex\ApiRoute\Contracts\ConsumerTrackerInterface;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;

class NullTracker implements ConsumerTrackerInterface, VersionTrackerInterface
{
    public function trackConsumer(string $version, string $consumer, int $status): void
    {
        // No-op
    }

    /**
     * @return array<int, array{consumer: string, total_requests: int, success_requests: int, error_requests: int, last_seen_at: string|null}>
     */
    public function getConsumerStats(string $version, int $days = 30): array
    {
        return [];
    }

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
