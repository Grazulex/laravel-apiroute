<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Resources\ThingResource;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    if (! class_exists(JsonApiResource::class)) {
        $this->markTestSkipped('JSON:API resources require Laravel 13.');
    }
    EndpointLifecycleResolver::flush();
});

test('adds version meta to a JSON:API resource document', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things/1', fn () => new ThingResource(['id' => 1, 'name' => 'One']));
    })->deprecated('2025-01-01')->sunset('2030-01-01')->setSuccessor('v2');

    $this->get('/api/v1/things/1', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.type', 'things')
        ->assertJsonPath('meta.api.version', 'v1')
        ->assertJsonPath('meta.api.status', 'deprecated')
        ->assertJsonPath('meta.api.deprecation', Carbon::parse('2025-01-01')->toIso8601String())
        ->assertJsonPath('meta.api.sunset', Carbon::parse('2030-01-01')->toIso8601String())
        ->assertJsonPath('meta.api.successor', 'v2');
});

test('endpoint lifecycle overrides version values in meta and adds links.successor', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('items', fn () => new ThingResource(['id' => 2, 'name' => 'Two']))
            ->deprecated(since: '2026-03-01', sunset: '2099-01-01', successor: 'https://api.example.com/v2/items');
    });

    $this->get('/api/v1/items', ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('meta.api.version', 'v1')
        ->assertJsonPath('meta.api.status', 'active')
        ->assertJsonPath('meta.api.deprecation', Carbon::parse('2026-03-01')->toIso8601String())
        ->assertJsonPath('meta.api.sunset', Carbon::parse('2099-01-01')->toIso8601String())
        ->assertJsonPath('meta.api.successor', 'https://api.example.com/v2/items')
        ->assertJsonPath('links.successor', 'https://api.example.com/v2/items');
});

test('omits absent values', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things/3', fn () => new ThingResource(['id' => 3, 'name' => 'Three']));
    });

    $response = $this->get('/api/v1/things/3', ['Accept' => 'application/vnd.api+json'])->assertOk();

    expect($response->json('meta.api'))->toBe(['version' => 'v1', 'status' => 'active'])
        ->and($response->json('links'))->toBeNull();
});
