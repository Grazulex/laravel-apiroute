<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Headers;

use Carbon\Carbon;

/**
 * Formats the value of the `Deprecation` header according to the
 * `apiroute.headers.deprecation_format` option.
 *
 * - `rfc7231` (default): HTTP-date, e.g. `Sun, 01 Jun 2025 00:00:00 GMT`
 *   (kept for backward compatibility).
 * - `rfc9745`: Structured Field Date, e.g. `@1748736000` (RFC 9745).
 *
 * @internal
 */
final class DeprecationHeaderValue
{
    public const FORMAT_RFC7231 = 'rfc7231';

    public const FORMAT_RFC9745 = 'rfc9745';

    public static function format(Carbon $date): string
    {
        if (self::configuredFormat() === self::FORMAT_RFC9745) {
            return '@' . $date->getTimestamp();
        }

        return $date->format(Carbon::RFC7231);
    }

    private static function configuredFormat(): string
    {
        $format = config('apiroute.headers.deprecation_format', self::FORMAT_RFC7231);

        return is_string($format) ? strtolower(trim($format)) : self::FORMAT_RFC7231;
    }
}
