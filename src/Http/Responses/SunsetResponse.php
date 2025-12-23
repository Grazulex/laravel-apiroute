<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Responses;

use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\JsonResponse;

class SunsetResponse extends JsonResponse
{
    public function __construct(VersionDefinition $version)
    {
        /** @var array<string, mixed> $config */
        $config = config('apiroute', []);

        /** @var array<string, string> $migrationGuides */
        $migrationGuides = $config['documentation']['migration_guides'] ?? [];

        $data = [
            'error' => 'api_version_sunset',
            'message' => "API version {$version->name()} is no longer available. Please upgrade to {$version->successor()}.",
            'sunset_date' => $version->sunsetDate()?->toIso8601String(),
            'successor' => $version->successor(),
            'migration_guide' => $migrationGuides[$version->name()] ?? null,
        ];

        $statusCode = (int) ($config['sunset']['status_code'] ?? 410);

        parent::__construct($data, $statusCode);
    }
}
