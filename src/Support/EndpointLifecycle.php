<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Support;

use Carbon\Carbon;
use Grazulex\ApiRoute\Attributes\Deprecated;

final readonly class EndpointLifecycle
{
    public function __construct(
        public ?Carbon $deprecatedAt,
        public ?Carbon $sunsetAt,
        public ?string $successor,
        public ?string $docs,
        public ?string $reason,
    ) {}

    /**
     * @param  array{since?: string|null, sunset?: string|null, successor?: string|null, docs?: string|null, reason?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deprecatedAt: isset($data['since']) ? Carbon::parse($data['since']) : null,
            sunsetAt: isset($data['sunset']) ? Carbon::parse($data['sunset']) : null,
            successor: $data['successor'] ?? null,
            docs: $data['docs'] ?? null,
            reason: $data['reason'] ?? null,
        );
    }

    public static function fromAttribute(Deprecated $attribute): self
    {
        return self::fromArray([
            'since' => $attribute->since,
            'sunset' => $attribute->sunset,
            'successor' => $attribute->successor,
            'docs' => $attribute->docs,
            'reason' => $attribute->reason,
        ]);
    }

    /**
     * Non-null fields of $override win over this instance's fields.
     */
    public function merge(self $override): self
    {
        return new self(
            deprecatedAt: $override->deprecatedAt ?? $this->deprecatedAt,
            sunsetAt: $override->sunsetAt ?? $this->sunsetAt,
            successor: $override->successor ?? $this->successor,
            docs: $override->docs ?? $this->docs,
            reason: $override->reason ?? $this->reason,
        );
    }

    public function isDeprecated(): bool
    {
        return $this->deprecatedAt instanceof Carbon || $this->sunsetAt instanceof Carbon;
    }

    public function isSunset(?Carbon $now = null): bool
    {
        return $this->sunsetAt instanceof Carbon && $this->sunsetAt->lessThanOrEqualTo($now ?? Carbon::now());
    }
}
