<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Commands;

use Carbon\Carbon;
use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Console\Command;

class ApiStatusCommand extends Command
{
    protected $signature = 'api:status
                            {--api-version= : Show details for a specific version}
                            {--json : Output as JSON}
                            {--routes : Include route list}';

    protected $description = 'Display the status of all API versions';

    public function handle(ApiRouteManager $manager, VersionTrackerInterface $tracker): int
    {
        $specificVersion = $this->option('api-version');

        if ($specificVersion !== null) {
            return $this->showVersionDetails($manager, $tracker, (string) $specificVersion);
        }

        return $this->showAllVersions($manager, $tracker);
    }

    private function showAllVersions(ApiRouteManager $manager, VersionTrackerInterface $tracker): int
    {
        $versions = $manager->versions();

        if ($versions->isEmpty()) {
            $this->warn('No API versions registered.');

            return self::SUCCESS;
        }

        $stats = $tracker->getAllStats(30);
        $totalRequests = array_sum(array_column($stats, 'total_requests'));
        $isJson = $this->option('json');

        $rows = $versions->map(function (VersionDefinition $version) use ($stats, $totalRequests, $isJson): array {
            $versionStats = $stats[$version->name()] ?? ['total_requests' => 0];
            $percentage = $totalRequests > 0
                ? round(($versionStats['total_requests'] / $totalRequests) * 100, 1)
                : 0;

            return [
                'version' => $version->name(),
                'status' => $isJson ? $this->formatStatusRaw($version) : $this->formatStatus($version),
                'deprecated' => $version->deprecationDate()?->format('Y-m-d') ?? '-',
                'sunset' => $version->sunsetDate()?->format('Y-m-d') ?? '-',
                'usage' => $percentage . '%',
            ];
        })->toArray();

        if ($isJson) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT) ?: '[]');

            return self::SUCCESS;
        }

        $this->table(
            ['Version', 'Status', 'Deprecated', 'Sunset', 'Usage (30d)'],
            $rows
        );

        $this->displayWarnings($versions);

        return self::SUCCESS;
    }

    private function showVersionDetails(ApiRouteManager $manager, VersionTrackerInterface $tracker, string $versionName): int
    {
        $version = $manager->getVersion($versionName);

        if ($version === null) {
            $this->error("Version '{$versionName}' not found.");

            return self::FAILURE;
        }

        $stats = $tracker->getStats($versionName, 30);
        $isJson = $this->option('json');

        $details = [
            ['Name', $version->name()],
            ['Status', $isJson ? $this->formatStatusRaw($version) : $this->formatStatus($version)],
            ['Deprecated', $version->deprecationDate()?->format('Y-m-d') ?? '-'],
            ['Sunset', $version->sunsetDate()?->format('Y-m-d') ?? '-'],
            ['Successor', $version->successor() ?? '-'],
            ['Documentation', $version->documentationUrl() ?? '-'],
            ['Rate Limit', $version->rateLimit_() !== null ? $version->rateLimit_() . '/min' : '-'],
            ['Requests (30d)', number_format($stats['total_requests'] ?? 0)],
        ];

        if ($isJson) {
            $this->line(json_encode($details, JSON_PRETTY_PRINT) ?: '[]');

            return self::SUCCESS;
        }

        $this->table(['Property', 'Value'], $details);

        return self::SUCCESS;
    }

    private function formatStatus(VersionDefinition $version): string
    {
        return match (true) {
            $version->isSunset() => '<fg=red>sunset</>',
            $version->isDeprecated() => '<fg=yellow>deprecated</>',
            $version->isBeta() => '<fg=blue>beta</>',
            $version->isActive() => '<fg=green>active</>',
            default => 'unknown',
        };
    }

    private function formatStatusRaw(VersionDefinition $version): string
    {
        return match (true) {
            $version->isSunset() => 'sunset',
            $version->isDeprecated() => 'deprecated',
            $version->isBeta() => 'beta',
            $version->isActive() => 'active',
            default => 'unknown',
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<string, VersionDefinition>  $versions
     */
    private function displayWarnings($versions): void
    {
        $this->newLine();

        foreach ($versions as $version) {
            if ($version->sunsetDate() !== null && $version->sunsetDate()->isFuture()) {
                $daysUntilSunset = Carbon::now()->diffInDays($version->sunsetDate());
                if ($daysUntilSunset <= 30) {
                    $this->warn("Warning: {$version->name()} will be sunset in {$daysUntilSunset} days ({$version->sunsetDate()->format('Y-m-d')})");
                }
            }
        }
    }
}
