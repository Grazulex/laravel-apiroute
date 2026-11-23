<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    EndpointLifecycleResolver::flush();

    config()->set('app.name', 'Acme');
    config()->set('app.url', 'https://example.test');
});

/**
 * @param  array<string, mixed>  $arguments
 *
 * @return array<string, mixed>
 */
function openApiJson(string $version, array $arguments = []): array
{
    Artisan::call('api:openapi', ['version' => $version, ...$arguments]);

    return json_decode(Artisan::output(), true);
}

test('generates the metadata and the paths of a version', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok')->name('users.index');
        Route::post('users', fn () => 'ok');
        Route::get('users/{id}', fn (string $id) => 'ok');
    });

    $json = openApiJson('v1');

    expect($json['openapi'])->toBe('3.0.3')
        ->and($json['info'])->toBe([
            'title' => 'Acme API',
            'version' => 'v1',
            'x-api-status' => 'active',
        ])
        ->and($json['servers'])->toBe([['url' => 'https://example.test/api/v1']])
        ->and($json)->not->toHaveKey('externalDocs')
        ->and(array_keys($json['paths']))->toBe(['/users', '/users/{id}'])
        ->and(array_keys($json['paths']['/users']))->toBe(['get', 'post'])
        ->and($json['paths']['/users']['get'])->toBe([
            'tags' => ['users'],
            'operationId' => 'users.index',
            'responses' => ['default' => ['description' => 'Response.']],
        ])
        ->and($json['paths']['/users']['post']['operationId'])->toBe('postUsers')
        ->and($json['paths']['/users/{id}']['get']['operationId'])->toBe('getUsersId')
        ->and($json['paths']['/users/{id}']['get']['parameters'])->toBe([
            ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
        ]);
});

test('a deprecated version marks every operation as deprecated', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    })
        ->deprecated('2025-06-01')
        ->sunset('2099-12-01')
        ->setSuccessor('v2')
        ->documentation('https://docs.example.com/v1');

    $json = openApiJson('v1');

    expect($json['info']['x-api-status'])->toBe('deprecated')
        ->and($json['info']['x-deprecated-at'])->toBe('2025-06-01')
        ->and($json['info']['x-sunset'])->toBe('2099-12-01')
        ->and($json['info']['x-successor'])->toBe('v2')
        ->and($json['info']['description'])->toBe(
            'This version is deprecated since 2025-06-01. It will be sunset on 2099-12-01. Use version v2 instead.'
        )
        ->and($json['externalDocs'])->toBe(['url' => 'https://docs.example.com/v1'])
        ->and($json['paths']['/users']['get']['deprecated'])->toBeTrue()
        ->and($json['paths']['/users']['get']['x-deprecated-at'])->toBe('2025-06-01')
        ->and($json['paths']['/users']['get']['x-sunset'])->toBe('2099-12-01');
});

test('a sunset version is reported as sunset', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    })->sunset('2020-01-01');

    $json = openApiJson('v1');

    expect($json['info']['x-api-status'])->toBe('sunset')
        ->and($json['info']['x-sunset'])->toBe('2020-01-01')
        ->and($json['info']['description'])->toBe('This version is deprecated. It was sunset on 2020-01-01.')
        ->and($json['paths']['/users']['get']['deprecated'])->toBeTrue()
        ->and($json['paths']['/users']['get']['x-sunset'])->toBe('2020-01-01');
});

test('a deprecated endpoint is marked in an active version', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
        Route::delete('users/{id}', fn (string $id) => 'ok')->deprecated(
            since: '2026-01-01',
            sunset: '2027-01-01',
            successor: '/api/v2/users',
            docs: 'https://docs.example.com/users',
            reason: 'Use the v2 endpoint',
        );
    });

    $json = openApiJson('v1');
    $operation = $json['paths']['/users/{id}']['delete'];

    expect($operation['deprecated'])->toBeTrue()
        ->and($operation['description'])->toBe('Use the v2 endpoint')
        ->and($operation['x-deprecated-at'])->toBe('2026-01-01')
        ->and($operation['x-sunset'])->toBe('2027-01-01')
        ->and($operation['x-successor'])->toBe('/api/v2/users')
        ->and($operation['externalDocs'])->toBe(['url' => 'https://docs.example.com/users'])
        ->and($json['paths']['/users']['get'])->not->toHaveKey('deprecated')
        ->and($json['paths']['/users']['get'])->not->toHaveKey('x-sunset');
});

test('endpoints deprecated through controller attributes are marked', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'index']);
    });

    $operation = openApiJson('v1')['paths']['/things']['get'];

    expect($operation['deprecated'])->toBeTrue()
        ->and($operation['x-deprecated-at'])->toBe('2026-01-01')
        ->and($operation['x-successor'])->toBe('/api/v2/legacy')
        ->and($operation)->not->toHaveKey('x-sunset');
});

test('the endpoint sunset date wins over the version one', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok')->deprecated(sunset: '2098-01-01');
        Route::get('posts', fn () => 'ok');
    })->deprecated('2025-06-01')->sunset('2099-12-01');

    $json = openApiJson('v1');

    expect($json['paths']['/users']['get']['x-sunset'])->toBe('2098-01-01')
        ->and($json['paths']['/users']['get']['x-deprecated-at'])->toBe('2025-06-01')
        ->and($json['paths']['/posts']['get']['x-sunset'])->toBe('2099-12-01');
});

test('a sunset date with a time is exported as a date-time', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    })->deprecated('2025-06-01')->sunset('2099-12-01 12:30:00');

    expect(openApiJson('v1')['info']['x-sunset'])->toStartWith('2099-12-01T12:30:00');
});

test('route constraints are translated into parameter schemas', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users/{id}/posts/{slug}', fn (string $id, string $slug) => 'ok')
            ->whereNumber('id')
            ->where('slug', '[a-z-]+');
    });

    $operation = openApiJson('v1')['paths']['/users/{id}/posts/{slug}']['get'];

    expect($operation['parameters'])->toBe([
        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
        ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'pattern' => '^(?:[a-z-]+)$']],
    ]);
});

test('a trailing optional parameter produces one path with it and one without', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('posts/{post}/comments/{comment?}', fn (string $post, ?string $comment = null) => 'ok')
            ->name('comments');
    });

    $paths = openApiJson('v1')['paths'];

    expect(array_keys($paths))->toBe(['/posts/{post}/comments', '/posts/{post}/comments/{comment}'])
        ->and(array_column($paths['/posts/{post}/comments/{comment}']['get']['parameters'], 'name'))->toBe(['post', 'comment'])
        ->and(array_column($paths['/posts/{post}/comments']['get']['parameters'], 'name'))->toBe(['post'])
        ->and($paths['/posts/{post}/comments/{comment}']['get']['operationId'])->toBe('comments')
        ->and($paths['/posts/{post}/comments']['get']['operationId'])->toBe('comments_2');
});

test('a route matching several methods gets one operation per method', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::match(['GET', 'POST'], 'search', fn () => 'ok')->name('search');
    });

    $path = openApiJson('v1')['paths']['/search'];

    expect(array_keys($path))->toBe(['get', 'post'])
        ->and($path['get']['operationId'])->toBe('search.get')
        ->and($path['post']['operationId'])->toBe('search.post');
});

test('the header strategy documents the version header', function (): void {
    config()->set('apiroute.strategy', 'header');

    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $json = openApiJson('v1');

    expect($json['servers'])->toBe([['url' => 'https://example.test/api']])
        ->and($json['paths']['/users']['get']['parameters'])->toBe([[
            'name' => 'X-API-Version',
            'in' => 'header',
            'required' => false,
            'description' => 'Selects the API version. Send `v1` to use this version.',
            'schema' => ['type' => 'string'],
            'example' => 'v1',
        ]]);
});

test('the query strategy documents the version query parameter', function (): void {
    config()->set('apiroute.strategy', 'query');

    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $parameter = openApiJson('v1')['paths']['/users']['get']['parameters'][0];

    expect($parameter['name'])->toBe('api_version')
        ->and($parameter['in'])->toBe('query');
});

test('configured domains become servers', function (): void {
    config()->set('apiroute.strategies.uri.domain', ['api.example.com', '{tenant}.example.com']);

    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });

    expect(openApiJson('v1')['servers'])->toBe([
        ['url' => 'https://api.example.com/api/v1'],
        [
            'url' => 'https://{tenant}.example.com/api/v1',
            'variables' => ['tenant' => ['default' => 'tenant']],
        ],
    ]);
});

test('--title and --server override the defaults', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $json = openApiJson('v1', ['--title' => 'Public API', '--server' => ['https://api.acme.test/v1']]);

    expect($json['info']['title'])->toBe('Public API')
        ->and($json['servers'])->toBe([['url' => 'https://api.acme.test/v1']]);
});

test('a version without routes exports an empty paths object', function (): void {
    ApiRoute::version('v1', function (): void {});

    Artisan::call('api:openapi', ['version' => 'v1']);
    $output = Artisan::output();

    expect($output)->toContain('"paths": {}')
        ->and(json_decode($output, true)['paths'])->toBe([]);
});

test('--output writes the specification to a file', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $directory = sys_get_temp_dir() . '/apiroute-openapi-' . uniqid();
    $file = $directory . '/nested/v1.json';

    try {
        $this->artisan('api:openapi', ['version' => 'v1', '--output' => $file])
            ->expectsOutputToContain('OpenAPI specification of v1 written to')
            ->assertSuccessful();

        $json = json_decode((string) file_get_contents($file), true);

        expect($json['info']['version'])->toBe('v1')
            ->and($json['paths'])->toHaveKey('/users');
    } finally {
        File::deleteDirectory($directory);
    }
});

test('fails on an unknown version', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $this->artisan('api:openapi', ['version' => 'v9'])
        ->expectsOutputToContain("Version 'v9' not found.")
        ->assertFailed();
});

test('does not alter the application routes', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('users', fn () => 'ok');
    });

    $router = app('router');
    $before = count(Route::getRoutes()->getRoutes());

    Artisan::call('api:openapi', ['version' => 'v1']);

    expect(Route::getFacadeRoot())->toBe($router)
        ->and(count(Route::getRoutes()->getRoutes()))->toBe($before);
});
