<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Commands;

use Carbon\Carbon;
use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\Events\VersionSunset;
use Illuminate\Console\Command;

class ApiSunsetCommand extends Command
{
    protected $signature = 'api:sunset
                            {version : The version to sunset}
                            {--remove-routes : Remove the routes for this version}
                            {--archive : Archive the controllers}';

    protected $description = 'Mark an API version as sunset (end of life)';

    public function handle(ApiRouteManager $manager): int
    {
        /** @var string $versionName */
        $versionName = $this->argument('version');

        $version = $manager->getVersion($versionName);

        if ($version === null) {
            $this->error("Version '{$versionName}' not found.");

            return self::FAILURE;
        }

        if (! $this->confirm("Are you sure you want to sunset version {$versionName}? This will make it unavailable.")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        // Apply sunset
        $version->sunset(Carbon::now());

        // Dispatch event
        event(new VersionSunset($version));

        $this->info("Version {$versionName} has been marked as sunset.");

        if ($this->option('archive')) {
            $this->archiveControllers($versionName);
        }

        $this->newLine();
        $this->warn('Note: This command only affects the runtime state.');
        $this->warn('Remove the version definition from routes/api.php to complete the sunset.');

        return self::SUCCESS;
    }

    private function archiveControllers(string $version): void
    {
        $normalizedVersion = strtoupper($version[0]) . substr($version, 1);
        $sourcePath = app_path("Http/Controllers/Api/{$normalizedVersion}");
        $archivePath = app_path("Http/Controllers/Api/_archived/{$normalizedVersion}");

        if (! is_dir($sourcePath)) {
            $this->warn("Controller directory not found: {$sourcePath}");

            return;
        }

        if (! is_dir(dirname($archivePath))) {
            mkdir(dirname($archivePath), 0755, true);
        }

        rename($sourcePath, $archivePath);
        $this->info("Controllers archived to: {$archivePath}");
    }
}
