<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tracking;

use Grazulex\ApiRoute\Contracts\ConsumerResolverInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Identifies the consumer by the authenticated user, then by an optional
 * client header (apiroute.tracking.consumers.header).
 */
class DefaultConsumerResolver implements ConsumerResolverInterface
{
    public function resolve(Request $request): ?string
    {
        $user = $request->user();

        if ($user instanceof Authenticatable) {
            $identifier = $user->getAuthIdentifier();

            if ((is_int($identifier) || is_string($identifier)) && (string) $identifier !== '') {
                return 'user:' . $identifier;
            }
        }

        $header = config('apiroute.tracking.consumers.header');

        if (is_string($header) && $header !== '') {
            $value = $request->header($header);

            if (is_string($value) && trim($value) !== '') {
                return 'client:' . trim($value);
            }
        }

        return null;
    }
}
