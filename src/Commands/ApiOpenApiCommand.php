<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Commands;

use Grazulex\ApiRoute\ApiRouteManager;
use Grazulex\ApiRoute\OpenApi\OpenApiGenerator;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Exports the OpenAPI specification of one API version: its paths and its
 * lifecycle (`deprecated: true`, `x-sunset`, ...). Schemas are not generated.
 */
class ApiOpenApiCommand extends Command
{
    protected $signature = 'api:openapi
                            {version : The version to export (e.g. v1)}
                            {--output= : Write the specification to this file instead of printing it}
                            {--title= : Title of the specification (defaults to the application name)}
                            {--server=* : Server URL to use instead of the one derived from the configuration}';

    protected $description = 'Generate the OpenAPI specification of an API version';

    public function handle(ApiRouteManager $manager, OpenApiGenerator $generator): int
    {
        $versionName = (string) $this->argument('version');

        $version = $manager->getVersion($versionName);
        if (! $version instanceof VersionDefinition) {
            $this->error("Version '{$versionName}' not found.");

            return self::FAILURE;
        }

        $title = $this->option('title');

        $servers = [];
        foreach ((array) $this->option('server') as $server) {
            if (is_string($server) && $server !== '') {
                $servers[] = $server;
            }
        }

        $document = $generator->generate($version, is_string($title) ? $title : null, $servers);

        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->error('The OpenAPI specification could not be encoded: ' . json_last_error_msg());

            return self::FAILURE;
        }

        $output = $this->option('output');

        if (! is_string($output) || $output === '') {
            $this->line($json);

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($output));

        if (File::put($output, $json . PHP_EOL) === false) {
            $this->error("Unable to write the OpenAPI specification to {$output}.");

            return self::FAILURE;
        }

        $this->info("OpenAPI specification of {$versionName} written to {$output}");

        return self::SUCCESS;
    }
}
