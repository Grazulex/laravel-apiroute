<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Commands;

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Contracts\ConsumerTrackerInterface;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Illuminate\Console\Command;

class ApiStatsCommand extends Command
{
    protected $signature = 'api:stats
                            {--period=30 : Number of days to analyze}
                            {--api-version= : Show stats for a specific version}
                            {--consumers : List the consumers (hashed) of the version given with --api-version}
                            {--json : Output as JSON}';

    protected $description = 'Display API version usage statistics';

    public function handle(ApiRouteManager $manager, VersionTrackerInterface $tracker): int
    {
        $period = (int) $this->option('period');

        $specificVersion = $this->option('api-version');

        if ($this->option('consumers')) {
            if ($specificVersion === null || $specificVersion === '') {
                $this->error('The --consumers option requires --api-version.');

                return self::FAILURE;
            }

            return $this->showConsumerStats($tracker, (string) $specificVersion, $period);
        }

        if ($specificVersion !== null) {
            return $this->showVersionStats($tracker, (string) $specificVersion, $period);
        }

        return $this->showAllStats($manager, $tracker, $period);
    }

    private function showAllStats(ApiRouteManager $manager, VersionTrackerInterface $tracker, int $period): int
    {
        $stats = $tracker->getAllStats($period);

        if ($stats === []) {
            $this->warn('No usage statistics available.');
            $this->line('Make sure tracking is enabled in config/apiroute.php');

            return self::SUCCESS;
        }

        $totalRequests = array_sum(array_column($stats, 'total_requests'));

        $this->info("API Version Usage Statistics (Last {$period} days)");
        $this->newLine();
        $this->line('Total Requests: ' . number_format($totalRequests));
        $this->newLine();

        $rows = [];
        foreach ($stats as $versionName => $versionStats) {
            $percentage = $totalRequests > 0
                ? round(($versionStats['total_requests'] / $totalRequests) * 100, 1)
                : 0;

            $version = $manager->getVersion($versionName);
            $statusLabel = $version?->isDeprecated() ? ' (deprecated)' : '';

            $rows[] = [
                'version' => $versionName . $statusLabel,
                'requests' => number_format($versionStats['total_requests']),
                'percentage' => $percentage . '%',
                'success' => number_format($versionStats['success_requests']),
                'errors' => number_format($versionStats['error_requests']),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT) ?: '[]');

            return self::SUCCESS;
        }

        $this->table(
            ['Version', 'Requests', 'Percentage', 'Success', 'Errors'],
            $rows
        );

        // Warnings for deprecated versions with high usage
        foreach ($stats as $versionName => $versionStats) {
            $version = $manager->getVersion($versionName);
            if ($version?->isDeprecated()) {
                $percentage = $totalRequests > 0
                    ? round(($versionStats['total_requests'] / $totalRequests) * 100, 1)
                    : 0;

                if ($percentage > 20) {
                    $this->newLine();
                    $this->warn("Warning: {$percentage}% of traffic still uses deprecated version {$versionName}");
                }
            }
        }

        return self::SUCCESS;
    }

    private function showConsumerStats(VersionTrackerInterface $tracker, string $version, int $period): int
    {
        if (! $tracker instanceof ConsumerTrackerInterface) {
            $this->warn('The configured tracking driver does not support per-consumer statistics.');

            return self::SUCCESS;
        }

        $stats = $tracker->getConsumerStats($version, $period);

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT) ?: '[]');

            return self::SUCCESS;
        }

        if ($stats === []) {
            $this->warn("No consumer statistics available for version '{$version}'.");
            $this->line('Make sure tracking.consumers is enabled in config/apiroute.php');

            return self::SUCCESS;
        }

        $this->info("Consumers of version {$version} (Last {$period} days): " . count($stats));
        $this->newLine();

        $rows = array_map(fn (array $row): array => [
            $row['consumer'],
            number_format($row['total_requests']),
            number_format($row['success_requests']),
            number_format($row['error_requests']),
            $row['last_seen_at'] ?? '-',
        ], $stats);

        $this->table(['Consumer', 'Requests', 'Success', 'Errors', 'Last seen'], $rows);

        return self::SUCCESS;
    }

    private function showVersionStats(VersionTrackerInterface $tracker, string $version, int $period): int
    {
        $stats = $tracker->getStats($version, $period);

        if ($stats === [] || ($stats['total_requests'] ?? 0) === 0) {
            $this->warn("No statistics available for version '{$version}'.");

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT) ?: '{}');

            return self::SUCCESS;
        }

        $this->info("Statistics for version {$version} (Last {$period} days)");
        $this->newLine();

        $this->table(['Metric', 'Value'], [
            ['Total Requests', number_format($stats['total_requests'])],
            ['Successful Requests', number_format($stats['success_requests'])],
            ['Failed Requests', number_format($stats['error_requests'])],
            ['Success Rate', $stats['total_requests'] > 0
                ? round(($stats['success_requests'] / $stats['total_requests']) * 100, 1) . '%'
                : 'N/A',
            ],
        ]);

        return self::SUCCESS;
    }
}
