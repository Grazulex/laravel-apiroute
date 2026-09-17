<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Http\JsonApi;
use Grazulex\ApiRoute\Http\Responses\JsonApiErrorDocument;
use Illuminate\Http\Request;

test('wanted detects the JSON:API media type in Accept or Content-Type', function (): void {
    expect(JsonApi::wanted(Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'application/vnd.api+json'])))->toBeTrue()
        ->and(JsonApi::wanted(Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'text/html, application/vnd.api+json;q=0.9'])))->toBeTrue()
        ->and(JsonApi::wanted(Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/vnd.api+json; ext="https://jsonapi.org/ext/atomic"'])))->toBeTrue()
        ->and(JsonApi::wanted(Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'application/json'])))->toBeFalse()
        ->and(JsonApi::wanted(Request::create('/', 'GET')))->toBeFalse();
});

test('error document has the JSON:API shape and media type', function (): void {
    $response = JsonApiErrorDocument::make(410, 'endpoint_sunset', 'Endpoint sunset', 'Use v2', ['successor' => 'http://x/v2', 'about' => 'http://docs'], ['sunset_at' => '2020-01-01T00:00:00+00:00']);

    expect($response->getStatusCode())->toBe(410)
        ->and($response->headers->get('Content-Type'))->toBe('application/vnd.api+json')
        ->and($response->getData(true))->toBe([
            'errors' => [[
                'status' => '410',
                'code' => 'endpoint_sunset',
                'title' => 'Endpoint sunset',
                'detail' => 'Use v2',
                'links' => ['successor' => 'http://x/v2', 'about' => 'http://docs'],
                'meta' => ['sunset_at' => '2020-01-01T00:00:00+00:00'],
            ]],
        ]);
});

test('error document omits empty members', function (): void {
    $data = JsonApiErrorDocument::make(404, 'version_not_found', 'Version not found')->getData(true);

    expect($data['errors'][0])->toBe(['status' => '404', 'code' => 'version_not_found', 'title' => 'Version not found']);
});

test('null link and meta values are dropped', function (): void {
    $data = JsonApiErrorDocument::make(410, 'x', 'X', null, ['successor' => null], ['a' => null, 'b' => 1])->getData(true);

    expect($data['errors'][0])->toBe(['status' => '410', 'code' => 'x', 'title' => 'X', 'meta' => ['b' => 1]]);
});
