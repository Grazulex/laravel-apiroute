<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Responses;

use Grazulex\ApiRoute\Http\JsonApi;
use Illuminate\Http\JsonResponse;

/**
 * Builds a single-error JSON:API document (https://jsonapi.org/format/#errors).
 */
final class JsonApiErrorDocument
{
    /**
     * @param  array<string, string|null>  $links
     * @param  array<string, mixed>  $meta
     */
    public static function make(int $status, string $code, string $title, ?string $detail = null, array $links = [], array $meta = []): JsonResponse
    {
        $error = ['status' => (string) $status, 'code' => $code, 'title' => $title];

        if ($detail !== null && $detail !== '') {
            $error['detail'] = $detail;
        }

        $links = array_filter($links, fn (?string $link): bool => $link !== null && $link !== '');
        if ($links !== []) {
            $error['links'] = $links;
        }

        $meta = array_filter($meta, fn (mixed $value): bool => $value !== null);
        if ($meta !== []) {
            $error['meta'] = $meta;
        }

        return new JsonResponse(['errors' => [$error]], $status, ['Content-Type' => JsonApi::MEDIA_TYPE]);
    }
}
