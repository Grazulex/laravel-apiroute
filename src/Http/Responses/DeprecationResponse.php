<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Responses;

use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\JsonResponse;

class DeprecationResponse extends JsonResponse
{
    public function __construct(VersionDefinition $version)
    {
        $data = [
            'warning' => 'api_version_deprecated',
            'message' => "API version {$version->name()} is deprecated.",
            'deprecation_date' => $version->deprecationDate()?->toIso8601String(),
            'sunset_date' => $version->sunsetDate()?->toIso8601String(),
            'successor' => $version->successor(),
            'documentation' => $version->documentationUrl(),
        ];

        parent::__construct($data, 200);
    }
}
