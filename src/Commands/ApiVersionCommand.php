<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ApiVersionCommand extends Command
{
    protected $signature = 'api:version
                            {version : The version to create (e.g., v3)}
                            {--copy-from= : Copy controllers from another version}
                            {--controllers= : Only create specific controllers (comma-separated)}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a new API version';

    public function __construct(
        private readonly Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        /** @var string $version */
        $version = $this->argument('version');
        $copyFrom = $this->option('copy-from');
        $force = (bool) $this->option('force');

        // Normalize version name
        $version = strtoupper($version[0]) . substr($version, 1); // v1 -> V1

        $controllerPath = app_path("Http/Controllers/Api/{$version}");

        if ($this->files->isDirectory($controllerPath) && ! $force) {
            $this->error("Directory {$controllerPath} already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        // Create directory
        $this->files->ensureDirectoryExists($controllerPath);

        if ($copyFrom !== null) {
            $this->copyFromVersion((string) $copyFrom, $version);
        } else {
            $this->createBaseController($version);
        }

        $this->info("API version {$version} scaffolded successfully!");
        $this->newLine();
        $this->line('Next steps:');
        $this->line("  1. Add your routes to routes/api.php using ApiRoute::version('{$version}', ...)");
        $this->line("  2. Create your controllers in app/Http/Controllers/Api/{$version}/");

        return self::SUCCESS;
    }

    private function copyFromVersion(string $source, string $target): void
    {
        $sourceVersion = strtoupper($source[0]) . substr($source, 1);
        $sourcePath = app_path("Http/Controllers/Api/{$sourceVersion}");

        if (! $this->files->isDirectory($sourcePath)) {
            $this->warn("Source version {$sourceVersion} not found. Creating empty version.");
            $this->createBaseController($target);

            return;
        }

        $targetPath = app_path("Http/Controllers/Api/{$target}");
        $controllers = $this->option('controllers');

        /** @var array<string>|null $controllerFilter */
        $controllerFilter = $controllers !== null
            ? array_map('trim', explode(',', (string) $controllers))
            : null;

        $files = $this->files->files($sourcePath);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Filter specific controllers if requested
            if ($controllerFilter !== null) {
                $controllerName = str_replace('Controller.php', '', $filename);
                if (! in_array($controllerName, $controllerFilter, true)) {
                    continue;
                }
            }

            $content = $this->files->get($file->getPathname());

            // Update namespace
            $content = str_replace(
                "namespace App\\Http\\Controllers\\Api\\{$sourceVersion};",
                "namespace App\\Http\\Controllers\\Api\\{$target};",
                $content
            );

            $this->files->put("{$targetPath}/{$filename}", $content);
            $this->info("Created: {$target}/{$filename}");
        }
    }

    private function createBaseController(string $version): void
    {
        $stub = $this->getStub('controller');

        $content = str_replace(
            ['{{ namespace }}', '{{ version }}'],
            ["App\\Http\\Controllers\\Api\\{$version}", $version],
            $stub
        );

        $path = app_path("Http/Controllers/Api/{$version}/Controller.php");
        $this->files->put($path, $content);

        $this->info("Created: {$version}/Controller.php");
    }

    private function getStub(string $name): string
    {
        $customStub = base_path("stubs/apiroute/{$name}.stub");

        if ($this->files->exists($customStub)) {
            return $this->files->get($customStub);
        }

        $packageStub = __DIR__ . "/../../stubs/{$name}.stub";

        if ($this->files->exists($packageStub)) {
            return $this->files->get($packageStub);
        }

        // Default stub
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace {{ namespace }};

use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    //
}
STUB;
    }
}
