<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => EndpointLifecycleResolver::flush());

test('lists deprecated endpoints in the table output', function (): void {
    Route::get('/api/v2/things', fn () => 'ok')->name('api.v2.things.index');
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
        Route::get('fresh', fn () => 'ok');
    });

    // Laravel's artisan()->expectsOutputToContain() mocks doWrite() with one
    // Mockery expectation per substring; Mockery::ExpectationDirector only
    // ever invokes the first matching expectation for a given call
    // (vendor/mockery/mockery/library/Mockery/ExpectationDirector.php). Since
    // a table row is written via a single writeln() call, checking several
    // substrings that live on the *same* rendered row in one artisan() run
    // means only the first-registered one is ever satisfied. Splitting across
    // separate artisan() calls keeps each check on its own mock.
    $this->artisan('api:status')
        ->expectsOutputToContain('Deprecated endpoints')
        ->expectsOutputToContain('api/v1/things')
        ->assertSuccessful();

    $this->artisan('api:status')
        ->expectsOutputToContain('2020-01-01')
        ->assertSuccessful();

    $this->artisan('api:status')
        ->expectsOutputToContain('SUNSET')
        ->assertSuccessful();
});

test('json output includes deprecated endpoints', function (): void {
    Route::get('/api/v2/things', fn () => 'ok')->name('api.v2.things.index');
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    Artisan::call('api:status', ['--json' => true]);
    $json = json_decode(Artisan::output(), true);

    expect($json['versions'])->toHaveKey('v1')
        ->and($json['versions']['v1']['version'])->toBe('v1')
        ->and($json['deprecated_endpoints'])->toBe([[
            'method' => 'GET',
            'uri' => 'api/v1/things',
            'version' => 'v1',
            'since' => '2026-01-01',
            'sunset' => '2020-01-01',
            'successor' => 'http://localhost/api/v2/things',
            'is_sunset' => true,
        ]]);
});

test('json output keeps the keyed-by-version object without deprecated endpoints', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('fresh', fn () => 'ok');
    });

    Artisan::call('api:status', ['--json' => true]);
    $json = json_decode(Artisan::output(), true);

    expect($json)->toHaveKey('v1')
        ->and($json)->not->toHaveKey('versions')
        ->and($json['v1']['version'])->toBe('v1');
});

test('parameterised successor route cannot be generated outside a request and shows a dash', function (): void {
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'successor route cannot be generated'));
    Route::get('/api/v2/things/{id}', fn (string $id) => 'ok')->name('api.v2.things.show');
    ApiRoute::version('v1', function (): void {
        Route::get('things/{id}', fn (string $id) => 'ok')->deprecated(since: '2026-01-01', successor: 'api.v2.things.show');
    });

    Artisan::call('api:status', ['--json' => true]);
    $json = json_decode(Artisan::output(), true);

    expect($json['deprecated_endpoints'][0]['uri'])->toBe('api/v1/things/{id}')
        ->and($json['deprecated_endpoints'][0]['successor'])->toBeNull();
});
