<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Commands;

use Carbon\Carbon;
use Grazulex\ApiRoute\ApiRouteManager;
use Illuminate\Console\Command;

class ApiDeprecateCommand extends Command
{
    protected $signature = 'api:deprecate
                            {version : The version to deprecate}
                            {--on= : Deprecation date (YYYY-MM-DD)}
                            {--sunset= : Sunset date (YYYY-MM-DD)}
                            {--successor= : Successor version}
                            {--notify : Send notifications}';

    protected $description = 'Mark an API version as deprecated';

    public function handle(ApiRouteManager $manager): int
    {
        /** @var string $versionName */
        $versionName = $this->argument('version');

        $version = $manager->getVersion($versionName);

        if ($version === null) {
            $this->error("Version '{$versionName}' not found.");

            return self::FAILURE;
        }

        $deprecationDate = $this->option('on')
            ? Carbon::parse((string) $this->option('on'))
            : Carbon::now();

        $sunsetDate = $this->option('sunset')
            ? Carbon::parse((string) $this->option('sunset'))
            : null;

        $successor = $this->option('successor');

        // Apply deprecation
        $version->deprecated($deprecationDate);

        if ($sunsetDate !== null) {
            $version->sunset($sunsetDate);
        }

        if ($successor !== null) {
            $version->setSuccessor((string) $successor);
        }

        $this->info("Version {$versionName} has been marked as deprecated.");
        $this->newLine();

        $this->table(['Property', 'Value'], [
            ['Version', $versionName],
            ['Deprecation Date', $deprecationDate->format('Y-m-d')],
            ['Sunset Date', $sunsetDate?->format('Y-m-d') ?? 'Not set'],
            ['Successor', $successor ?? 'Not set'],
        ]);

        $this->newLine();
        $this->warn('Note: This command only affects the runtime state.');
        $this->warn('Update your routes/api.php to persist these changes:');
        $this->newLine();

        $code = "ApiRoute::version('{$versionName}', function () { ... })";
        $code .= "\n    ->deprecated('{$deprecationDate->format('Y-m-d')}')";

        if ($sunsetDate !== null) {
            $code .= "\n    ->sunset('{$sunsetDate->format('Y-m-d')}')";
        }

        if ($successor !== null) {
            $code .= "\n    ->successor('{$successor}')";
        }

        $code .= ';';

        $this->line($code);

        return self::SUCCESS;
    }
}
