<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Headers;

use Carbon\Carbon;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VersionHeaders
{
    public function addToResponse(Response $response, VersionDefinition $version, ?Request $request = null): Response
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
            $successorUrl = $this->buildSuccessorUrl($version, $request);
            $response->headers->set(
                'Link',
                "<{$successorUrl}>; rel=\"successor-version\""
            );
        }

        return $response;
    }

    private function buildSuccessorUrl(VersionDefinition $version, ?Request $request = null): string
    {
        /** @var string $strategy */
        $strategy = config('apiroute.strategy', 'uri');

        /** @var array<string, mixed> $config */
        $config = config('apiroute.strategies.uri', []);

        $prefix = $config['prefix'] ?? 'api';
        $successor = $version->successor();
        $currentVersion = $version->name();

        // If we have a request and using URI strategy, build the full path
        if ($request !== null && $strategy === 'uri') {
            $currentPath = $request->path();

            // Replace the current version in the path with the successor
            $successorPath = preg_replace(
                '/\b' . preg_quote($currentVersion, '/') . '\b/',
                (string) $successor,
                $currentPath,
                1
            );

            if ($successorPath !== null && $successorPath !== $currentPath) {
                return url($successorPath);
            }
        }

        // Fallback to base URL with just prefix and successor
        $baseUrl = $prefix !== '' ? "{$prefix}/{$successor}" : (string) $successor;

        return url($baseUrl);
    }
}
