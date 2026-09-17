<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Resources;

use Grazulex\ApiRoute\Support\ApiVersionContext;
use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\VersionDefinition;
use Illuminate\Http\Request;

/**
 * Adds API version metadata to a Laravel 13 JsonApiResource document:
 * top-level meta.api {version, status, deprecation, sunset, successor} and
 * links.successor. Endpoint-level deprecation wins over version-level.
 */
// @phpstan-ignore trait.unused (only consumed by tests/Support/Resources/ThingResource.php, a Laravel 13-only JsonApiResource subclass outside the analysed `src` path)
trait InteractsWithApiVersion
{
    /**
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return array_merge_recursive(parent::with($request), $this->apiVersionDocumentMembers($request));
    }

    /**
     * @return array{meta?: array{api: array<string, string>}, links?: array{successor: string}}
     */
    protected function apiVersionDocumentMembers(Request $request): array
    {
        $version = app(ApiVersionContext::class)->getVersion();
        $resolver = app(EndpointLifecycleResolver::class);
        $lifecycle = $resolver->forRequest($request);

        $api = [];
        $successorLink = null;

        if ($version instanceof VersionDefinition) {
            $api['version'] = $version->name();
            $api['status'] = $version->status()->value;
            $api['deprecation'] = $version->deprecationDate()?->toIso8601String();
            $api['sunset'] = $version->sunsetDate()?->toIso8601String();
            $api['successor'] = $version->successor();
        }

        if ($lifecycle instanceof EndpointLifecycle) {
            $successorLink = $resolver->resolveSuccessorUrl($lifecycle);
            $api['deprecation'] = $lifecycle->deprecatedAt?->toIso8601String() ?? ($api['deprecation'] ?? null);
            $api['sunset'] = $lifecycle->sunsetAt?->toIso8601String() ?? ($api['sunset'] ?? null);
            $api['successor'] = $successorLink ?? ($api['successor'] ?? null);
        }

        $api = array_filter($api, fn (?string $value): bool => $value !== null);

        $members = [];
        if ($api !== []) {
            $members['meta'] = ['api' => $api];
        }
        if ($successorLink !== null) {
            $members['links'] = ['successor' => $successorLink];
        }

        return $members;
    }
}
