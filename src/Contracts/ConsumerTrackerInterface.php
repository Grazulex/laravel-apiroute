<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Contracts;

interface ConsumerTrackerInterface
{
    /**
     * Track a request made by a consumer, identified by its hashed identifier.
     */
    public function trackConsumer(string $version, string $consumer, int $status): void;

    /**
     * Get per-consumer usage statistics for a version, most active consumers first.
     *
     * @return array<int, array{consumer: string, total_requests: int, success_requests: int, error_requests: int, last_seen_at: string|null}>
     */
    public function getConsumerStats(string $version, int $days = 30): array;
}
