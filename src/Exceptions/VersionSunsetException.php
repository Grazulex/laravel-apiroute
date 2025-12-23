<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Exceptions;

use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionSunsetException extends ApiRouteException
{
    public function __construct(
        public readonly VersionDefinition $version
    ) {
        parent::__construct("API version '{$version->name()}' has been sunset.");
    }

    public function render(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $config */
        $config = config('apiroute');

        /** @var array<string, string> $migrationGuides */
        $migrationGuides = $config['documentation']['migration_guides'] ?? [];

        return response()->json([
            'error' => 'api_version_sunset',
            'message' => "API version {$this->version->name()} is no longer available.",
            'sunset_date' => $this->version->sunsetDate()?->toIso8601String(),
            'successor' => $this->version->successor(),
            'migration_guide' => $migrationGuides[$this->version->name()] ?? null,
        ], (int) ($config['sunset']['status_code'] ?? 410));
    }
}
