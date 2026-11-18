<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tracking;

use Carbon\Carbon;
use Grazulex\ApiRoute\Contracts\ConsumerTrackerInterface;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Illuminate\Support\Facades\DB;

class DatabaseTracker implements ConsumerTrackerInterface, VersionTrackerInterface
{
    public function trackConsumer(string $version, string $consumer, int $status): void
    {
        $table = $this->consumersTable();

        $now = Carbon::now();
        $isSuccess = $status >= 200 && $status < 400;

        $conditions = [
            'version' => $version,
            'consumer_hash' => $consumer,
            'date' => $now->toDateString(),
        ];

        $affected = DB::table($table)
            ->where($conditions)
            ->update([
                'requests_count' => DB::raw('requests_count + 1'),
                'success_count' => DB::raw('success_count + ' . ($isSuccess ? 1 : 0)),
                'error_count' => DB::raw('error_count + ' . ($isSuccess ? 0 : 1)),
                'last_seen_at' => $now,
                'updated_at' => $now,
            ]);

        if ($affected === 0) {
            DB::table($table)->insert([
                ...$conditions,
                'requests_count' => 1,
                'success_count' => $isSuccess ? 1 : 0,
                'error_count' => $isSuccess ? 0 : 1,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array<int, array{consumer: string, total_requests: int, success_requests: int, error_requests: int, last_seen_at: string|null}>
     */
    public function getConsumerStats(string $version, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days)->toDateString();

        $results = DB::table($this->consumersTable())
            ->where('version', $version)
            ->where('date', '>=', $startDate)
            ->groupBy('consumer_hash')
            ->selectRaw('consumer_hash')
            ->selectRaw('SUM(requests_count) as total_requests')
            ->selectRaw('SUM(success_count) as success_requests')
            ->selectRaw('SUM(error_count) as error_requests')
            ->selectRaw('MAX(last_seen_at) as last_seen_at')
            ->orderByDesc('total_requests')
            ->orderBy('consumer_hash')
            ->get();

        $stats = [];
        foreach ($results as $result) {
            $lastSeen = $result->last_seen_at ?? null;

            $stats[] = [
                'consumer' => (string) $result->consumer_hash,
                'total_requests' => (int) $result->total_requests,
                'success_requests' => (int) $result->success_requests,
                'error_requests' => (int) $result->error_requests,
                'last_seen_at' => $lastSeen === null ? null : Carbon::parse((string) $lastSeen)->toIso8601String(),
            ];
        }

        return $stats;
    }

    private function consumersTable(): string
    {
        $table = config('apiroute.tracking.consumers.table', 'api_version_consumers');

        return is_string($table) && $table !== '' ? $table : 'api_version_consumers';
    }

    public function track(
        string $version,
        string $endpoint,
        string $method,
        int $status
    ): void {
        /** @var string $table */
        $table = config('apiroute.tracking.table', 'api_version_stats');

        /** @var string $aggregate */
        $aggregate = config('apiroute.tracking.aggregate', 'hourly');

        $date = Carbon::now()->toDateString();
        $hour = $aggregate === 'hourly' ? Carbon::now()->hour : null;

        $isSuccess = $status >= 200 && $status < 400;

        $conditions = [
            'version' => $version,
            'endpoint' => $endpoint,
            'method' => $method,
            'date' => $date,
            'hour' => $hour,
        ];

        $affected = DB::table($table)
            ->where($conditions)
            ->update([
                'requests_count' => DB::raw('requests_count + 1'),
                'success_count' => DB::raw('success_count + ' . ($isSuccess ? 1 : 0)),
                'error_count' => DB::raw('error_count + ' . ($isSuccess ? 0 : 1)),
                'updated_at' => Carbon::now(),
            ]);

        if ($affected === 0) {
            DB::table($table)->insert([
                ...$conditions,
                'requests_count' => 1,
                'success_count' => $isSuccess ? 1 : 0,
                'error_count' => $isSuccess ? 0 : 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(string $version, int $days = 30): array
    {
        /** @var string $table */
        $table = config('apiroute.tracking.table', 'api_version_stats');

        $startDate = Carbon::now()->subDays($days)->toDateString();

        $result = DB::table($table)
            ->where('version', $version)
            ->where('date', '>=', $startDate)
            ->selectRaw('SUM(requests_count) as total_requests')
            ->selectRaw('SUM(success_count) as success_requests')
            ->selectRaw('SUM(error_count) as error_requests')
            ->first();

        return [
            'version' => $version,
            'period_days' => $days,
            'total_requests' => (int) ($result->total_requests ?? 0),
            'success_requests' => (int) ($result->success_requests ?? 0),
            'error_requests' => (int) ($result->error_requests ?? 0),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllStats(int $days = 30): array
    {
        /** @var string $table */
        $table = config('apiroute.tracking.table', 'api_version_stats');

        $startDate = Carbon::now()->subDays($days)->toDateString();

        $results = DB::table($table)
            ->where('date', '>=', $startDate)
            ->groupBy('version')
            ->selectRaw('version')
            ->selectRaw('SUM(requests_count) as total_requests')
            ->selectRaw('SUM(success_count) as success_requests')
            ->selectRaw('SUM(error_count) as error_requests')
            ->get();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result->version] = [
                'version' => $result->version,
                'period_days' => $days,
                'total_requests' => (int) $result->total_requests,
                'success_requests' => (int) $result->success_requests,
                'error_requests' => (int) $result->error_requests,
            ];
        }

        return $stats;
    }
}
