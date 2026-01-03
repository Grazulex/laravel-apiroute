<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Listeners;

use Grazulex\ApiRoute\Http\Headers\VersionHeaders;
use Grazulex\ApiRoute\Support\ApiVersionContext;
use Illuminate\Foundation\Http\Events\RequestHandled;

/**
 * Listener that adds API version headers to all responses.
 *
 * This listener is triggered after the response is created but before it's sent,
 * ensuring that version headers are added to ALL responses including error responses
 * (401, 403, 404, 500, etc.).
 */
class AddVersionHeadersToResponse
{
    public function __construct(
        private readonly ApiVersionContext $context,
        private readonly VersionHeaders $headers
    ) {}

    /**
     * Handle the RequestHandled event.
     */
    public function handle(RequestHandled $event): void
    {
        // Only add headers if a version was resolved
        if (! $this->context->hasVersion()) {
            return;
        }

        $version = $this->context->getVersion();
        $request = $this->context->getRequest();

        if ($version === null) {
            return;
        }

        // Add version headers to the response
        $this->headers->addToResponse($event->response, $version, $request);
    }
}
