<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests\Support\Resources;

use Grazulex\ApiRoute\Http\Resources\InteractsWithApiVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

if (class_exists(JsonApiResource::class)) {
    final class ThingResource extends JsonApiResource
    {
        use InteractsWithApiVersion;

        public function toType(Request $request): string
        {
            return 'things';
        }

        public function toId(Request $request): string
        {
            return (string) $this->resource['id'];
        }

        public function toAttributes(Request $request): array
        {
            return ['name' => $this->resource['name']];
        }
    }
}
