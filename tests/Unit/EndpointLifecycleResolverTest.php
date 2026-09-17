<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedActionController;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Grazulex\ApiRoute\Tests\Support\Controllers\PlainController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    EndpointLifecycleResolver::flush();
    $this->resolver = app(EndpointLifecycleResolver::class);
});

test('returns null for a route without attributes or macro', function (): void {
    $route = Route::get('/plain', [PlainController::class, 'index']);

    expect($this->resolver->forRoute($route))->toBeNull();
});

test('reads a class-level attribute', function (): void {
    $route = Route::get('/legacy', [DeprecatedClassController::class, 'index']);

    $lifecycle = $this->resolver->forRoute($route);

    expect($lifecycle?->deprecatedAt?->toDateString())->toBe('2026-01-01')
        ->and($lifecycle?->successor)->toBe('/api/v2/legacy')
        ->and($lifecycle?->docs)->toBe('https://docs.example.com/legacy');
});

test('method attribute overrides class attribute field by field', function (): void {
    $route = Route::get('/legacy-sunset', [DeprecatedClassController::class, 'sunset']);

    $lifecycle = $this->resolver->forRoute($route);

    expect($lifecycle?->deprecatedAt?->toDateString())->toBe('2026-01-01') // from class
        ->and($lifecycle?->sunsetAt?->toDateString())->toBe('2020-01-01')  // from method
        ->and($lifecycle?->successor)->toBe('api.v2.things.index')          // method wins
        ->and($lifecycle?->docs)->toBe('https://docs.example.com/legacy')   // from class
        ->and($lifecycle?->reason)->toBe('Use things v2');
});

test('reads a method-level attribute only', function (): void {
    $route = Route::get('/items', [DeprecatedActionController::class, 'deprecated']);

    expect($this->resolver->forRoute($route)?->successor)->toBe('https://api.example.com/v2/items');
    expect($this->resolver->forRoute(Route::get('/fresh', [DeprecatedActionController::class, 'fresh'])))->toBeNull();
});

test('macro wins over attributes and works on closures', function (): void {
    $closure = Route::get('/closure', fn () => 'ok')->deprecated(since: '2026-05-05', successor: '/api/v2/closure');
    $controller = Route::get('/override', [DeprecatedClassController::class, 'index'])->deprecated(successor: '/api/v9/override');

    expect($this->resolver->forRoute($closure)?->deprecatedAt?->toDateString())->toBe('2026-05-05')
        ->and($this->resolver->forRoute($closure)?->successor)->toBe('/api/v2/closure')
        ->and($this->resolver->forRoute($controller)?->successor)->toBe('/api/v9/override')
        ->and($this->resolver->forRoute($controller)?->deprecatedAt)->toBeNull();
});

test('memoises attribute reflection per action', function (): void {
    $route = Route::get('/memo', [DeprecatedActionController::class, 'deprecated']);

    $first = $this->resolver->forRoute($route);
    $second = $this->resolver->forRoute($route);

    expect($second)->toBe($first);
});

test('forRequest returns null without a matched route', function (): void {
    expect($this->resolver->forRequest(request()))->toBeNull();
});

test('resolves a named route successor', function (): void {
    Route::get('/api/v2/things', fn () => 'ok')->name('api.v2.things.index');
    $lifecycle = EndpointLifecycle::fromArray(['successor' => 'api.v2.things.index']);

    expect($this->resolver->resolveSuccessorUrl($lifecycle))->toBe('http://localhost/api/v2/things');
});

test('generates a named successor route with the parameters of the context route', function (): void {
    Route::get('/api/v2/things/{id}', fn () => 'ok')->name('api.v2.things.show');
    $context = Route::get('/api/v1/things/{id}', fn () => 'ok');
    $context->bind(Request::create('/api/v1/things/7'));
    $lifecycle = EndpointLifecycle::fromArray(['successor' => 'api.v2.things.show']);

    expect($this->resolver->resolveSuccessorUrl($lifecycle, $context))->toBe('http://localhost/api/v2/things/7');
});

test('logs and returns null when the successor route needs parameters the context lacks', function (): void {
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'successor route cannot be generated') && $context['successor'] === 'api.v2.things.show');
    Route::get('/api/v2/things/{id}', fn () => 'ok')->name('api.v2.things.show');
    $context = Route::get('/api/v1/things', fn () => 'ok');
    $lifecycle = EndpointLifecycle::fromArray(['successor' => 'api.v2.things.show']);

    expect($this->resolver->resolveSuccessorUrl($lifecycle, $context))->toBeNull();
});

test('resolves a path and an absolute url successor', function (): void {
    $path = EndpointLifecycle::fromArray(['successor' => '/api/v2/things']);
    $url = EndpointLifecycle::fromArray(['successor' => 'https://api.example.com/v2']);

    expect($this->resolver->resolveSuccessorUrl($path))->toBe('http://localhost/api/v2/things')
        ->and($this->resolver->resolveSuccessorUrl($url))->toBe('https://api.example.com/v2')
        ->and($this->resolver->resolveSuccessorUrl(EndpointLifecycle::fromArray([])))->toBeNull();
});

test('throws on an unknown named route in the testing environment', function (): void {
    $lifecycle = EndpointLifecycle::fromArray(['successor' => 'api.v2.missing.index']);

    $this->resolver->resolveSuccessorUrl($lifecycle);
})->throws(InvalidArgumentException::class, 'api.v2.missing.index');

test('logs and returns null on an unknown named route outside local/testing', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'unknown successor route'));
    $lifecycle = EndpointLifecycle::fromArray(['successor' => 'api.v2.missing.index']);

    expect($this->resolver->resolveSuccessorUrl($lifecycle))->toBeNull();
});
