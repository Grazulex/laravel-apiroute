<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tracking;

use RuntimeException;

/**
 * Turns raw consumer identifiers into keyed, non-reversible hashes.
 *
 * An HMAC is used instead of a plain hash so that small identifier spaces
 * (numeric user ids, ...) cannot be recovered by brute force without the key.
 */
class ConsumerHasher
{
    public function canHash(): bool
    {
        return $this->key() !== '';
    }

    public function hash(string $identifier): string
    {
        $key = $this->key();

        if ($key === '') {
            throw new RuntimeException('No key available to hash API consumer identifiers. Set apiroute.tracking.consumers.hash_key or app.key.');
        }

        return hash_hmac('sha256', $identifier, $key);
    }

    private function key(): string
    {
        $key = config('apiroute.tracking.consumers.hash_key');

        if (! is_string($key) || $key === '') {
            $key = config('app.key');
        }

        return is_string($key) ? $key : '';
    }
}
