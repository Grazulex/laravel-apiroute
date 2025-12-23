<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Headers;

use Carbon\Carbon;
use Grazulex\ApiRoute\VersionDefinition;
use Symfony\Component\HttpFoundation\Response;

class VersionHeaders
{
    public function addToResponse(Response $response, VersionDefinition $version): Response
    {
        /** @var array<string, mixed> $config */
        $config = config('apiroute.headers', []);

        if (($config['enabled'] ?? true) === false) {
            return $response;
        }

        /** @var array<string, bool> $include */
        $include = $config['include'] ?? [];

        // X-API-Version
        if ($include['version'] ?? true) {
            $response->headers->set('X-API-Version', $version->name());
        }

        // X-API-Version-Status
        if ($include['status'] ?? true) {
            $response->headers->set('X-API-Version-Status', $version->status()->value);
        }

        // Deprecation header (RFC 8594)
        if (($include['deprecation'] ?? true) && $version->deprecationDate() !== null) {
            $response->headers->set(
                'Deprecation',
                $version->deprecationDate()->format(Carbon::RFC7231)
            );
        }

        // Sunset header (RFC 7231)
        if (($include['sunset'] ?? true) && $version->sunsetDate() !== null) {
            $response->headers->set(
                'Sunset',
                $version->sunsetDate()->format(Carbon::RFC7231)
            );
        }

        // Link to successor
        if (($include['successor_link'] ?? true) && $version->successor() !== null) {
            $successorUrl = $this->buildSuccessorUrl($version);
            $response->headers->set(
                'Link',
                "<{$successorUrl}>; rel=\"successor-version\""
            );
        }

        return $response;
    }

    private function buildSuccessorUrl(VersionDefinition $version): string
    {
        /** @var array<string, mixed> $config */
        $config = config('apiroute.strategies.uri', []);

        $prefix = $config['prefix'] ?? 'api';
        $successor = $version->successor();

        return url("{$prefix}/{$successor}");
    }
}
