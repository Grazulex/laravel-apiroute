<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Exceptions\InvalidVersionException;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

const JSON_API = ['Accept' => 'application/vnd.api+json'];

beforeEach(function (): void {
    EndpointLifecycleResolver::flush();
    Route::get('/api/v2/things', fn () => 'ok')->name('api.v2.things.index');
});

test('unknown version is a JSON:API error document when negotiated', function (): void {
    config(['apiroute.strategy' => 'header', 'apiroute.fallback.enabled' => false]);
    ApiRoute::version('v1', function (): void {
        Route::get('test', fn () => 'ok');
    });

    $this->withHeader('X-API-Version', 'v9')->get('/api/test', JSON_API)
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertExactJson(['errors' => [[
            'status' => '404',
            'code' => 'version_not_found',
            'title' => 'API version not found',
            'detail' => "API version 'v9' not found.",
            'meta' => ['requested_version' => 'v9', 'available_versions' => ['v1']],
        ]]]);
});

test('unknown version keeps the legacy json body without negotiation', function (): void {
    config(['apiroute.strategy' => 'header', 'apiroute.fallback.enabled' => false]);
    ApiRoute::version('v1', function (): void {
        Route::get('test', fn () => 'ok');
    });

    $response = $this->withHeader('X-API-Version', 'v9')->get('/api/test');

    $response->assertStatus(404);
    expect($response->getContent())->toBe('{"error":"version_not_found","message":"API version \'v9\' not found.","requested_version":"v9","available_versions":["v1"]}');
});

test('sunset version is a JSON:API error document when negotiated', function (): void {
    $sunset = Carbon::now()->subDay()->startOfSecond();
    ApiRoute::version('v1', function (): void {
        Route::get('test', fn () => 'ok');
    })->sunset($sunset)->setSuccessor('v2');

    $this->get('/api/v1/test', JSON_API)
        ->assertStatus(410)
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('errors.0.code', 'version_sunset')
        ->assertJsonPath('errors.0.status', '410')
        ->assertJsonPath('errors.0.meta.sunset_date', $sunset->toIso8601String())
        ->assertJsonPath('errors.0.meta.successor', 'v2');
});

test('sunset endpoint is a JSON:API error document when negotiated', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $this->get('/api/v1/things', JSON_API)
        ->assertStatus(410)
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertExactJson(['errors' => [[
            'status' => '410',
            'code' => 'endpoint_sunset',
            'title' => 'Endpoint sunset',
            'detail' => 'Use things v2',
            'links' => ['about' => 'https://docs.example.com/legacy', 'successor' => 'http://localhost/api/v2/things'],
            'meta' => ['sunset_at' => Carbon::parse('2020-01-01')->toIso8601String()],
        ]]])
        ->assertHeader('X-API-Endpoint-Status', 'sunset');
});

test('sunset endpoint keeps the plain json body without negotiation', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $this->get('/api/v1/things')->assertStatus(410)->assertHeader('Content-Type', 'application/json')->assertJson(['error' => 'endpoint_sunset']);
});

test('invalid version exception renders json and JSON:API', function (): void {
    $exception = new InvalidVersionException('v-bad', 'Must match v{n}.');

    $plain = $exception->render(Request::create('/api'));
    $jsonApi = $exception->render(Request::create('/api', 'GET', server: ['HTTP_ACCEPT' => 'application/vnd.api+json']));

    expect($plain->getStatusCode())->toBe(400)
        ->and($plain->getData(true))->toBe(['error' => 'invalid_version', 'message' => "Invalid API version 'v-bad'. Must match v{n}."])
        ->and($jsonApi->getData(true)['errors'][0])->toMatchArray(['status' => '400', 'code' => 'invalid_version', 'title' => 'Invalid API version']);
});
