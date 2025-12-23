<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Exceptions;

use Grazulex\ApiRoute\Facades\ApiRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersionNotFoundException extends ApiRouteException
{
    public function __construct(
        public readonly string $requestedVersion
    ) {
        parent::__construct("API version '{$requestedVersion}' not found.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => 'version_not_found',
            'message' => $this->getMessage(),
            'requested_version' => $this->requestedVersion,
            'available_versions' => ApiRoute::versions()->pluck('name')->toArray(),
        ], 404);
    }
}
