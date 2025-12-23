<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tracking;

use Carbon\Carbon;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Illuminate\Support\Facades\Redis;

class RedisTracker implements VersionTrackerInterface
{
    private const PREFIX = 'apiroute:stats:';

    public function track(
        string $version,
        string $endpoint,
        string $method,
        int $status
    ): void {
        $date = Carbon::now()->toDateString();
        $key = self::PREFIX . $version . ':' . $date;

        $isSuccess = $status >= 200 && $status < 400;

        Redis::hincrby($key, 'total', 1);
        Redis::hincrby($key, $isSuccess ? 'success' : 'error', 1);
        Redis::hincrby($key, 'endpoint:' . $method . ':' . $endpoint, 1);

        // Set expiry to 90 days
        Redis::expire($key, 60 * 60 * 24 * 90);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(string $version, int $days = 30): array
    {
        $totalRequests = 0;
        $successRequests = 0;
        $errorRequests = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $key = self::PREFIX . $version . ':' . $date;

            $total = (int) Redis::hget($key, 'total');
            $success = (int) Redis::hget($key, 'success');
            $error = (int) Redis::hget($key, 'error');

            $totalRequests += $total;
            $successRequests += $success;
            $errorRequests += $error;
        }

        return [
            'version' => $version,
            'period_days' => $days,
            'total_requests' => $totalRequests,
            'success_requests' => $successRequests,
            'error_requests' => $errorRequests,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllStats(int $days = 30): array
    {
        // This is a simplified implementation
        // In production, you'd want to scan for all version keys
        $stats = [];

        $pattern = self::PREFIX . '*:' . Carbon::now()->toDateString();
        $keys = Redis::keys($pattern);

        /** @var string[] $keys */
        foreach ($keys as $key) {
            // Extract version from key
            $parts = explode(':', $key);
            if (count($parts) >= 3) {
                $version = $parts[2];
                if (! isset($stats[$version])) {
                    $stats[$version] = $this->getStats($version, $days);
                }
            }
        }

        return $stats;
    }
}
