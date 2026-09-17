<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Attributes;

use Attribute;

/**
 * Marks an endpoint (controller class or action) as deprecated.
 *
 * Dates are parsed with Carbon::parse(). `successor` is a named route, a path
 * starting with "/" or an absolute URL. `docs` becomes a Link rel="deprecation".
 * PHP 8.4's native #[\Deprecated] may be used alongside this attribute.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Deprecated
{
    public function __construct(
        public ?string $since = null,
        public ?string $sunset = null,
        public ?string $successor = null,
        public ?string $docs = null,
        public ?string $reason = null,
    ) {}
}
