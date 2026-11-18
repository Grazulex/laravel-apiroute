<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Contracts;

use Illuminate\Http\Request;

interface ConsumerResolverInterface
{
    /**
     * Resolve the raw identifier of the consumer (user, client, ...) that made the request.
     *
     * The value is hashed before being stored. Return null when the consumer
     * cannot be identified: the request is then not tracked per consumer.
     */
    public function resolve(Request $request): ?string;
}
