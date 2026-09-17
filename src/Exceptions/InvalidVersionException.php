<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Exceptions;

use Grazulex\ApiRoute\Http\JsonApi;
use Grazulex\ApiRoute\Http\Responses\JsonApiErrorDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidVersionException extends ApiRouteException
{
    public function __construct(string $version, string $reason = '')
    {
        $message = "Invalid API version '{$version}'.";
        if ($reason !== '') {
            $message .= " {$reason}";
        }

        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        if (JsonApi::wanted($request)) {
            return JsonApiErrorDocument::make(400, 'invalid_version', 'Invalid API version', $this->getMessage());
        }

        return response()->json(['error' => 'invalid_version', 'message' => $this->getMessage()], 400);
    }
}
