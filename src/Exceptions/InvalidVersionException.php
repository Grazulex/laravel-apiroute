<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Exceptions;

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
}
