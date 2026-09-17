<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Exceptions;

use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EndpointSunsetException extends ApiRouteException
{
    public const DEFAULT_MESSAGE = 'This endpoint is no longer available.';

    public function __construct(
        public readonly EndpointLifecycle $lifecycle,
        public readonly ?string $successorUrl,
    ) {
        parent::__construct($lifecycle->reason ?? self::DEFAULT_MESSAGE);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => 'endpoint_sunset',
            'message' => $this->getMessage(),
            'sunset_at' => $this->lifecycle->sunsetAt?->toIso8601String(),
            'successor' => $this->successorUrl,
            'docs' => $this->lifecycle->docs,
        ], $this->statusCode());
    }

    protected function statusCode(): int
    {
        return (int) config('apiroute.sunset.status_code', 410);
    }
}
