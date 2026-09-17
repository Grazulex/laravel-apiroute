<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http;

use Illuminate\Http\Request;

final class JsonApi
{
    public const MEDIA_TYPE = 'application/vnd.api+json';

    /**
     * True when the client speaks JSON:API (Accept or Content-Type).
     */
    public static function wanted(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');
        $contentType = (string) $request->headers->get('Content-Type', '');

        return str_contains($accept, self::MEDIA_TYPE) || str_starts_with($contentType, self::MEDIA_TYPE);
    }
}
