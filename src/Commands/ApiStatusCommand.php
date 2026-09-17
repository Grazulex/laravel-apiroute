<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Commands;

use Carbon\Carbon;
use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;
use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ApiStatusCommand extends Command
{
    protected $signature = 'api:status
                            {--api-version= : Show details for a specific version}
                            {--json : Output as JSON}
                            {--routes : Include route list}';

    protected $description = 'Display the status of all API versions';

    public function handle(ApiRouteManager $manager, VersionTrackerInterface $tracker, EndpointLifecycleResolver $resolver): int
    {
        $specificVersion = $this->option('api-version');

        if ($specificVersion !== null) {
            return $this->showVersionDetails($manager, $tracker, (string) $specificVersion);
        }

        return $this->showAllVersions($manager, $tracker, $resolver);
    }

    private function showAllVersions(ApiRouteManager $manager, VersionTrackerInterface $tracker, EndpointLifecycleResolver $resolver): int
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
        })->values()->toArray();

        $endpoints = $this->deprecatedEndpoints($manager, $resolver);

        if ($isJson) {
            $payload = $endpoints === [] ? $rows : ['versions' => $rows, 'deprecated_endpoints' => $endpoints];
            $this->line(json_encode($payload, JSON_PRETTY_PRINT) ?: '[]');

            return self::SUCCESS;
        }

        $this->table(
            ['Version', 'Status', 'Deprecated', 'Sunset', 'Usage (30d)'],
            $rows
        );

        $this->displayWarnings($versions);

        if ($endpoints !== []) {
            $this->newLine();
            $this->info('Deprecated endpoints');
            $this->table(
                ['Method', 'URI', 'Version', 'Since', 'Sunset', 'Successor'],
                array_map(fn (array $row): array => [
                    $row['method'],
                    $row['uri'],
                    $row['version'],
                    $row['since'] ?? '-',
                    ($row['sunset'] ?? '-') . ($row['is_sunset'] ? ' (SUNSET)' : ''),
                    $row['successor'] ?? '-',
                ], $endpoints),
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{method: string, uri: string, version: string, since: string|null, sunset: string|null, successor: string|null, is_sunset: bool}>
     */
    private function deprecatedEndpoints(ApiRouteManager $manager, EndpointLifecycleResolver $resolver): array
    {
        $rows = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $lifecycle = $resolver->forRoute($route);
            if (! $lifecycle instanceof EndpointLifecycle) {
                continue;
            }

            $rows[] = [
                'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'version' => $this->versionOfRoute($route, $manager),
                'since' => $lifecycle->deprecatedAt?->format('Y-m-d'),
                'sunset' => $lifecycle->sunsetAt?->format('Y-m-d'),
                'successor' => $resolver->resolveSuccessorUrl($lifecycle),
                'is_sunset' => $lifecycle->isSunset(),
            ];
        }

        return $rows;
    }

    private function versionOfRoute(\Illuminate\Routing\Route $route, ApiRouteManager $manager): string
    {
        foreach ($manager->versions() as $version) {
            if (preg_match('#(^|/)' . preg_quote($version->name(), '#') . '(/|$)#', $route->uri()) === 1) {
                return $version->name();
            }
        }

        return '-';
    }

    private function showVersionDetails(ApiRouteManager $manager, VersionTrackerInterface $tracker, string $versionName): int
    {
        $version = $manager->getVersion($versionName);

        if (! $version instanceof VersionDefinition) {
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
     * @param  Collection<string, VersionDefinition>  $versions
     */
    private function displayWarnings(Collection $versions): void
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
