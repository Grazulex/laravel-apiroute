<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Exceptions;

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Http\JsonApi;
use Grazulex\ApiRoute\Http\Responses\JsonApiErrorDocument;
use Grazulex\ApiRoute\VersionDefinition;
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
        $available = ApiRoute::versions()->map(fn (VersionDefinition $version): string => $version->name())->values()->toArray();

        if (JsonApi::wanted($request)) {
            return JsonApiErrorDocument::make(404, 'version_not_found', 'API version not found', $this->getMessage(), [], [
                'requested_version' => $this->requestedVersion,
                'available_versions' => $available,
            ]);
        }

        return response()->json([
            'error' => 'version_not_found',
            'message' => $this->getMessage(),
            'requested_version' => $this->requestedVersion,
            'available_versions' => $available,
        ], 404);
    }
}
