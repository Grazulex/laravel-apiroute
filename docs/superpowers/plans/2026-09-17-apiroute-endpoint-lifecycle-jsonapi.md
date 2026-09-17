# ApiRoute Endpoint Lifecycle & JSON:API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a single endpoint be deprecated with `#[Deprecated]` (or a route macro), emit the matching `Deprecation`/`Sunset`/`Link` headers and 410 rejection, and speak JSON:API for error documents (L12+L13) and resource metadata (L13 trait).

**Architecture:** A readonly `EndpointLifecycle` value is resolved per matched route by `EndpointLifecycleResolver` (route action key from the macro, else PHP attributes on the controller class/method, memoised). `EnforceEndpointSunset` middleware (appended to every version group) throws `EndpointSunsetException` when a sunset date has passed under the existing `apiroute.sunset.action` policy; `EndpointHeaders` adds endpoint headers from the existing `RequestHandled` listener after the version headers. JSON:API error documents are produced by content negotiation inside the exceptions' `render()`; an opt-in trait enriches Laravel 13 `JsonApiResource` documents.

**Tech Stack:** PHP 8.3, Laravel 12/13 (`illuminate/*`), Carbon 3, Pest 3/4, PHPStan (larastan), Pint.

**Spec:** `docs/superpowers/specs/2026-09-17-apiroute-endpoint-lifecycle-jsonapi-design.md`

## Global Constraints

- Runtime constraints stay `illuminate/* ^12.0|^13.0`, `php ^8.3`. No new runtime dependency.
- Everything must run on Laravel 12 **and** 13. The only L13-specific code is the trait `InteractsWithApiVersion`, whose tests are skipped when `Illuminate\Http\Resources\JsonApi\JsonApiResource` does not exist.
- Without JSON:API negotiation, existing JSON error bodies must stay **byte-identical** to today (`VersionNotFoundException`, `VersionSunsetException`).
- Only one new config key: `apiroute.headers.include.endpoint_status` (default `true`).
- Sunset policy for endpoints reuses `config('apiroute.sunset.action')` (`reject`|`warn`|`allow`, default `reject`) and `config('apiroute.sunset.status_code')` (default 410).
- Header date format for endpoints = the one used by versions today: `Carbon::RFC7231`.
- Attribute class is `Grazulex\ApiRoute\Attributes\Deprecated`; macro is `deprecated()` on `Illuminate\Routing\Route`; middleware alias is `api.endpoint-sunset`.
- Test controllers live under `tests/Support/` (namespace `Grazulex\ApiRoute\Tests\Support\…`, autoloaded by `Grazulex\ApiRoute\Tests\` → `tests/`).
- Every code step must keep `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` and `vendor/bin/pest` green. Run them before each commit. Existing tests: 93; the count must never decrease.
- Commit messages: sober, English, conventional prefix. No AI attribution of any kind in commits, PR, changelog or docs.
- Work on branch `feature/endpoint-lifecycle` (already exists, contains the spec). Never touch tags.
- Temporary files, if any, go in `/tmp/apiroute-*` (the machine's `/tmp` is shared).
- Existing patterns to follow: exceptions extend `Grazulex\ApiRoute\Exceptions\ApiRouteException` and expose `render(Request): JsonResponse`; feature tests build versions with `ApiRoute::version('v1', function (): void { Route::get(...); })` and call `$this->get('/api/v1/...')`; `tests/TestCase.php` clears `ApiVersionContext` in `setUp()`.

---

## File map

| File | Responsibility |
|---|---|
| `src/Attributes/Deprecated.php` | the PHP attribute |
| `src/Support/EndpointLifecycle.php` | readonly value object |
| `src/Support/EndpointLifecycleResolver.php` | route → lifecycle (macro key, attributes), memo, successor URL |
| `src/ApiRouteServiceProvider.php` | macro, alias, singletons |
| `src/Http/Headers/EndpointHeaders.php` | endpoint headers on the response |
| `src/Listeners/AddVersionHeadersToResponse.php` | calls `EndpointHeaders` after `VersionHeaders` |
| `src/Middleware/EnforceEndpointSunset.php` | 410 policy for endpoints |
| `src/Exceptions/EndpointSunsetException.php` | 410 body (JSON / JSON:API) |
| `src/ApiRouteManager.php` | appends `api.endpoint-sunset` to version groups |
| `src/Http/JsonApi.php` | content negotiation |
| `src/Http/Responses/JsonApiErrorDocument.php` | JSON:API error document builder |
| `src/Exceptions/{VersionNotFoundException,VersionSunsetException,InvalidVersionException}.php` | JSON:API branch in `render()` |
| `src/Http/Resources/InteractsWithApiVersion.php` | L13 `JsonApiResource` trait |
| `src/Commands/ApiStatusCommand.php` | "Deprecated endpoints" section |
| `config/apiroute.php` | `headers.include.endpoint_status` |
| `tests/Support/Controllers/*.php` | annotated test controllers |
| `README.md`, `CHANGELOG.md` | docs |

---

### Task 1: `Deprecated` attribute and `EndpointLifecycle` value object

**Files:**
- Create: `src/Attributes/Deprecated.php`, `src/Support/EndpointLifecycle.php`
- Test: `tests/Unit/EndpointLifecycleTest.php`

**Interfaces:**
- Produces:
  - `#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)] final readonly class Deprecated { public function __construct(public ?string $since = null, public ?string $sunset = null, public ?string $successor = null, public ?string $docs = null, public ?string $reason = null) }`
  - `final readonly class EndpointLifecycle { public function __construct(public ?Carbon $deprecatedAt, public ?Carbon $sunsetAt, public ?string $successor, public ?string $docs, public ?string $reason); public static function fromArray(array $data): self; public static function fromAttribute(Deprecated $attribute): self; public function merge(self $override): self; public function isDeprecated(): bool; public function isSunset(?Carbon $now = null): bool; }`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/EndpointLifecycleTest.php`:

```php
<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Attributes\Deprecated;
use Grazulex\ApiRoute\Support\EndpointLifecycle;

test('fromArray parses dates and keeps strings', function (): void {
    $lifecycle = EndpointLifecycle::fromArray([
        'since' => '2026-06-01',
        'sunset' => '2026-12-01',
        'successor' => 'api.v2.users.index',
        'docs' => 'https://docs.example.com/migrate',
        'reason' => 'Use v2',
    ]);

    expect($lifecycle->deprecatedAt?->toDateString())->toBe('2026-06-01')
        ->and($lifecycle->sunsetAt?->toDateString())->toBe('2026-12-01')
        ->and($lifecycle->successor)->toBe('api.v2.users.index')
        ->and($lifecycle->docs)->toBe('https://docs.example.com/migrate')
        ->and($lifecycle->reason)->toBe('Use v2');
});

test('fromArray tolerates missing keys', function (): void {
    $lifecycle = EndpointLifecycle::fromArray(['since' => '2026-06-01']);

    expect($lifecycle->sunsetAt)->toBeNull()
        ->and($lifecycle->successor)->toBeNull()
        ->and($lifecycle->isDeprecated())->toBeTrue()
        ->and($lifecycle->isSunset())->toBeFalse();
});

test('fromAttribute mirrors the attribute fields', function (): void {
    $lifecycle = EndpointLifecycle::fromAttribute(new Deprecated(since: '2026-06-01', successor: '/api/v2/users'));

    expect($lifecycle->deprecatedAt?->toDateString())->toBe('2026-06-01')
        ->and($lifecycle->successor)->toBe('/api/v2/users');
});

test('merge lets non-null override fields win', function (): void {
    $class = EndpointLifecycle::fromArray(['since' => '2026-01-01', 'successor' => 'class-successor', 'docs' => 'https://class']);
    $method = EndpointLifecycle::fromArray(['sunset' => '2026-12-01', 'successor' => 'method-successor']);

    $merged = $class->merge($method);

    expect($merged->deprecatedAt?->toDateString())->toBe('2026-01-01')
        ->and($merged->sunsetAt?->toDateString())->toBe('2026-12-01')
        ->and($merged->successor)->toBe('method-successor')
        ->and($merged->docs)->toBe('https://class');
});

test('isSunset compares against now inclusively', function (): void {
    $lifecycle = EndpointLifecycle::fromArray(['sunset' => '2026-12-01 00:00:00']);

    expect($lifecycle->isSunset(Carbon::parse('2026-11-30 23:59:59')))->toBeFalse()
        ->and($lifecycle->isSunset(Carbon::parse('2026-12-01 00:00:00')))->toBeTrue()
        ->and($lifecycle->isSunset(Carbon::parse('2027-01-01')))->toBeTrue();
});

test('isDeprecated is false when nothing is set', function (): void {
    expect(EndpointLifecycle::fromArray([])->isDeprecated())->toBeFalse();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Unit/EndpointLifecycleTest.php`
Expected: FAIL — class `Grazulex\ApiRoute\Support\EndpointLifecycle` not found.

- [ ] **Step 3: Implement**

Create `src/Attributes/Deprecated.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Attributes;

use Attribute;

/**
 * Marks an endpoint (controller class or action) as deprecated.
 *
 * Dates are parsed with Carbon::parse(). `successor` is a named route, a path
 * starting with "/" or an absolute URL. `docs` becomes a Link rel="deprecation".
 * PHP 8.4's native #[\Deprecated] may be used alongside this attribute.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Deprecated
{
    public function __construct(
        public ?string $since = null,
        public ?string $sunset = null,
        public ?string $successor = null,
        public ?string $docs = null,
        public ?string $reason = null,
    ) {}
}
```

Create `src/Support/EndpointLifecycle.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Support;

use Carbon\Carbon;
use Grazulex\ApiRoute\Attributes\Deprecated;

final readonly class EndpointLifecycle
{
    public function __construct(
        public ?Carbon $deprecatedAt,
        public ?Carbon $sunsetAt,
        public ?string $successor,
        public ?string $docs,
        public ?string $reason,
    ) {}

    /**
     * @param  array{since?: string|null, sunset?: string|null, successor?: string|null, docs?: string|null, reason?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deprecatedAt: isset($data['since']) ? Carbon::parse($data['since']) : null,
            sunsetAt: isset($data['sunset']) ? Carbon::parse($data['sunset']) : null,
            successor: $data['successor'] ?? null,
            docs: $data['docs'] ?? null,
            reason: $data['reason'] ?? null,
        );
    }

    public static function fromAttribute(Deprecated $attribute): self
    {
        return self::fromArray([
            'since' => $attribute->since,
            'sunset' => $attribute->sunset,
            'successor' => $attribute->successor,
            'docs' => $attribute->docs,
            'reason' => $attribute->reason,
        ]);
    }

    /**
     * Non-null fields of $override win over this instance's fields.
     */
    public function merge(self $override): self
    {
        return new self(
            deprecatedAt: $override->deprecatedAt ?? $this->deprecatedAt,
            sunsetAt: $override->sunsetAt ?? $this->sunsetAt,
            successor: $override->successor ?? $this->successor,
            docs: $override->docs ?? $this->docs,
            reason: $override->reason ?? $this->reason,
        );
    }

    public function isDeprecated(): bool
    {
        return $this->deprecatedAt instanceof Carbon || $this->sunsetAt instanceof Carbon;
    }

    public function isSunset(?Carbon $now = null): bool
    {
        return $this->sunsetAt instanceof Carbon && $this->sunsetAt->lessThanOrEqualTo($now ?? Carbon::now());
    }
}
```

- [ ] **Step 4: Run tests, PHPStan, Pint**

Run: `vendor/bin/pest tests/Unit/EndpointLifecycleTest.php` — expected 6 passed.
Run: `vendor/bin/phpstan analyse --no-progress && vendor/bin/pint --test`.

- [ ] **Step 5: Commit**

```bash
git add src/Attributes/Deprecated.php src/Support/EndpointLifecycle.php tests/Unit/EndpointLifecycleTest.php
git commit -m "feat: Deprecated attribute and EndpointLifecycle value object"
```

---

### Task 2: Route macro and `EndpointLifecycleResolver`

**Files:**
- Create: `src/Support/EndpointLifecycleResolver.php`, `tests/Support/Controllers/DeprecatedClassController.php`, `tests/Support/Controllers/DeprecatedActionController.php`, `tests/Support/Controllers/PlainController.php`
- Modify: `src/ApiRouteServiceProvider.php` (macro + singleton)
- Test: `tests/Unit/EndpointLifecycleResolverTest.php`

**Interfaces:**
- Consumes: `EndpointLifecycle`, `Deprecated` (Task 1).
- Produces:
  - `Illuminate\Routing\Route::deprecated(?string $since = null, ?string $sunset = null, ?string $successor = null, ?string $docs = null, ?string $reason = null): Route` (macro; stores under action key `apiroute.deprecated`).
  - `final class EndpointLifecycleResolver { public function forRoute(Route $route): ?EndpointLifecycle; public function forRequest(Request $request): ?EndpointLifecycle; public function resolveSuccessorUrl(EndpointLifecycle $lifecycle): ?string; public static function flush(): void; }` — bound as a singleton.

- [ ] **Step 1: Create the test controllers**

`tests/Support/Controllers/DeprecatedClassController.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests\Support\Controllers;

use Grazulex\ApiRoute\Attributes\Deprecated;
use Illuminate\Http\JsonResponse;

#[Deprecated(since: '2026-01-01', successor: '/api/v2/legacy', docs: 'https://docs.example.com/legacy')]
final class DeprecatedClassController
{
    public function index(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    #[Deprecated(sunset: '2020-01-01', successor: 'api.v2.things.index', reason: 'Use things v2')]
    public function sunset(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
```

`tests/Support/Controllers/DeprecatedActionController.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests\Support\Controllers;

use Grazulex\ApiRoute\Attributes\Deprecated;
use Illuminate\Http\JsonResponse;

final class DeprecatedActionController
{
    public static int $reflections = 0;

    #[Deprecated(since: '2026-03-01', sunset: '2099-01-01', successor: 'https://api.example.com/v2/items')]
    public function deprecated(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function fresh(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
```

`tests/Support/Controllers/PlainController.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests\Support\Controllers;

use Illuminate\Http\JsonResponse;

final class PlainController
{
    public function index(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
```

- [ ] **Step 2: Write the failing tests**

Create `tests/Unit/EndpointLifecycleResolverTest.php`:

```php
<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedActionController;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Grazulex\ApiRoute\Tests\Support\Controllers\PlainController;
use Illuminate\Routing\Route as RoutingRoute;
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
    $lifecycle = \Grazulex\ApiRoute\Support\EndpointLifecycle::fromArray(['successor' => 'api.v2.things.index']);

    expect($this->resolver->resolveSuccessorUrl($lifecycle))->toBe('http://localhost/api/v2/things');
});

test('resolves a path and an absolute url successor', function (): void {
    $path = \Grazulex\ApiRoute\Support\EndpointLifecycle::fromArray(['successor' => '/api/v2/things']);
    $url = \Grazulex\ApiRoute\Support\EndpointLifecycle::fromArray(['successor' => 'https://api.example.com/v2']);

    expect($this->resolver->resolveSuccessorUrl($path))->toBe('http://localhost/api/v2/things')
        ->and($this->resolver->resolveSuccessorUrl($url))->toBe('https://api.example.com/v2')
        ->and($this->resolver->resolveSuccessorUrl(\Grazulex\ApiRoute\Support\EndpointLifecycle::fromArray([])))->toBeNull();
});

test('throws on an unknown named route in the testing environment', function (): void {
    $lifecycle = \Grazulex\ApiRoute\Support\EndpointLifecycle::fromArray(['successor' => 'api.v2.missing.index']);

    $this->resolver->resolveSuccessorUrl($lifecycle);
})->throws(InvalidArgumentException::class, 'api.v2.missing.index');

test('logs and returns null on an unknown named route outside local/testing', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    Log::shouldReceive('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'unknown successor route'));
    $lifecycle = \Grazulex\ApiRoute\Support\EndpointLifecycle::fromArray(['successor' => 'api.v2.missing.index']);

    expect($this->resolver->resolveSuccessorUrl($lifecycle))->toBeNull();
});
```

Note on `RoutingRoute` import: remove it if unused after writing; Pint will flag unused imports.

- [ ] **Step 3: Run to verify failure**

Run: `vendor/bin/pest tests/Unit/EndpointLifecycleResolverTest.php`
Expected: FAIL — resolver class not found / macro `deprecated` does not exist.

- [ ] **Step 4: Implement the resolver**

Create `src/Support/EndpointLifecycleResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Support;

use Grazulex\ApiRoute\Attributes\Deprecated;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route as Router;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Finds the deprecation metadata of a route: the `deprecated()` macro first,
 * then #[Deprecated] attributes on the controller class and action.
 */
final class EndpointLifecycleResolver
{
    public const ACTION_KEY = 'apiroute.deprecated';

    /** @var array<string, EndpointLifecycle|null> keyed by action name */
    private static array $cache = [];

    public function __construct(private readonly Application $app) {}

    public function forRoute(Route $route): ?EndpointLifecycle
    {
        $macro = $route->getAction(self::ACTION_KEY);
        if (is_array($macro)) {
            return EndpointLifecycle::fromArray($macro);
        }

        $action = $route->getActionName();
        if (! str_contains($action, '@')) {
            return null;
        }

        if (array_key_exists($action, self::$cache)) {
            return self::$cache[$action];
        }

        return self::$cache[$action] = $this->fromAttributes($action);
    }

    public function forRequest(Request $request): ?EndpointLifecycle
    {
        $route = $request->route();

        return $route instanceof Route ? $this->forRoute($route) : null;
    }

    public function resolveSuccessorUrl(EndpointLifecycle $lifecycle): ?string
    {
        $successor = $lifecycle->successor;

        if ($successor === null || $successor === '') {
            return null;
        }

        if (Router::has($successor)) {
            return route($successor);
        }

        if (str_starts_with($successor, '/')) {
            return url($successor);
        }

        if ($this->looksLikeRouteName($successor)) {
            if ($this->app->environment(['local', 'testing'])) {
                throw new InvalidArgumentException("[apiroute] unknown successor route [{$successor}].");
            }

            Log::warning('[apiroute] unknown successor route', ['successor' => $successor]);

            return null;
        }

        return $successor;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    private function fromAttributes(string $action): ?EndpointLifecycle
    {
        [$class, $method] = explode('@', $action, 2);

        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $lifecycle = $this->attributeOf($reflection);

        if ($reflection->hasMethod($method)) {
            $methodLifecycle = $this->attributeOf($reflection->getMethod($method));
            $lifecycle = $lifecycle instanceof EndpointLifecycle && $methodLifecycle instanceof EndpointLifecycle
                ? $lifecycle->merge($methodLifecycle)
                : ($methodLifecycle ?? $lifecycle);
        }

        return $lifecycle;
    }

    private function attributeOf(ReflectionClass|ReflectionMethod $target): ?EndpointLifecycle
    {
        $attributes = $target->getAttributes(Deprecated::class);

        return $attributes === [] ? null : EndpointLifecycle::fromAttribute($attributes[0]->newInstance());
    }

    private function looksLikeRouteName(string $value): bool
    {
        return ! str_contains($value, '/') && ! str_contains($value, '://') && str_contains($value, '.');
    }
}
```

- [ ] **Step 5: Register macro and singleton in the provider**

In `src/ApiRouteServiceProvider.php`:
- `register()`: add `$this->app->singleton(EndpointLifecycleResolver::class);` next to the `ApiVersionContext` singleton, with `use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;`.
- `registerMacros()`: append, with `use Illuminate\Routing\Route as RoutingRoute;`:

```php
        RoutingRoute::macro('deprecated', function (
            ?string $since = null,
            ?string $sunset = null,
            ?string $successor = null,
            ?string $docs = null,
            ?string $reason = null,
        ): RoutingRoute {
            /** @var RoutingRoute $this */
            $action = $this->getAction();
            $action[EndpointLifecycleResolver::ACTION_KEY] = compact('since', 'sunset', 'successor', 'docs', 'reason');

            return $this->setAction($action);
        });
```

If PHPStan complains about `$this` inside the macro closure, add a `@phpstan-ignore-line`-free fix: declare the closure with `/** @var \Illuminate\Routing\Route $this */` as above (larastan understands macros with this docblock). If the IDE/PHPStan needs it, add `@method static \Illuminate\Routing\Route deprecated(...)` nowhere — macros on `Route` instances are dynamic by design.

- [ ] **Step 6: Run tests, PHPStan, Pint**

Run: `vendor/bin/pest tests/Unit/EndpointLifecycleResolverTest.php` — expected 11 passed.
Run: `vendor/bin/pest | tail -3` — 93 + 17 passed.
Run: `vendor/bin/phpstan analyse --no-progress && vendor/bin/pint --test`.

- [ ] **Step 7: Commit**

```bash
git add src/Support/EndpointLifecycleResolver.php src/ApiRouteServiceProvider.php tests/Support tests/Unit/EndpointLifecycleResolverTest.php
git commit -m "feat: resolve endpoint lifecycle from attributes or the deprecated() route macro"
```

---

### Task 3: Endpoint headers on responses

**Files:**
- Create: `src/Http/Headers/EndpointHeaders.php`
- Modify: `src/Listeners/AddVersionHeadersToResponse.php`, `src/ApiRouteServiceProvider.php` (singleton), `config/apiroute.php`
- Test: `tests/Feature/EndpointHeadersTest.php`

**Interfaces:**
- Consumes: `EndpointLifecycleResolver::forRequest()`, `resolveSuccessorUrl()` (Task 2).
- Produces: `final class EndpointHeaders { public function addToResponse(Response $response, EndpointLifecycle $lifecycle): Response; }` (singleton); config `apiroute.headers.include.endpoint_status`.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/EndpointHeadersTest.php`:

```php
<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedActionController;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => EndpointLifecycleResolver::flush());

test('deprecated endpoint in an active version gets deprecation headers', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    });

    $response = $this->get('/api/v1/items');

    $response->assertOk()
        ->assertHeader('X-API-Version', 'v1')
        ->assertHeader('X-API-Version-Status', 'active')
        ->assertHeader('X-API-Endpoint-Status', 'deprecated')
        ->assertHeader('Deprecation', Carbon::parse('2026-03-01')->format(Carbon::RFC7231))
        ->assertHeader('Sunset', Carbon::parse('2099-01-01')->format(Carbon::RFC7231))
        ->assertHeader('Link', '<https://api.example.com/v2/items>; rel="successor-version"');
});

test('docs adds a Link rel="deprecation" combined with the successor link', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('legacy', [DeprecatedClassController::class, 'index']);
    });

    $this->get('/api/v1/legacy')
        ->assertOk()
        ->assertHeader('Link', '<http://localhost/api/v2/legacy>; rel="successor-version", <https://docs.example.com/legacy>; rel="deprecation"');
});

test('fresh endpoint has no endpoint headers', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('fresh', [DeprecatedActionController::class, 'fresh']);
    });

    $response = $this->get('/api/v1/fresh');

    $response->assertOk()->assertHeaderMissing('X-API-Endpoint-Status')->assertHeaderMissing('Deprecation');
});

test('endpoint values override version values, version status stays', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    })->deprecated('2025-01-01')->sunset('2030-01-01')->setSuccessor('v2');

    $this->get('/api/v1/items')
        ->assertOk()
        ->assertHeader('X-API-Version-Status', 'deprecated')
        ->assertHeader('Deprecation', Carbon::parse('2026-03-01')->format(Carbon::RFC7231))
        ->assertHeader('Sunset', Carbon::parse('2099-01-01')->format(Carbon::RFC7231))
        ->assertHeader('Link', '<https://api.example.com/v2/items>; rel="successor-version"');
});

test('closure route with the deprecated macro gets the same headers', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('closure', fn () => response()->json(['ok' => true]))->deprecated(since: '2026-05-05', successor: '/api/v2/closure');
    });

    $this->get('/api/v1/closure')
        ->assertOk()
        ->assertHeader('X-API-Endpoint-Status', 'deprecated')
        ->assertHeader('Deprecation', Carbon::parse('2026-05-05')->format(Carbon::RFC7231))
        ->assertHeader('Link', '<http://localhost/api/v2/closure>; rel="successor-version"');
});

test('endpoint_status header can be disabled by config', function (): void {
    config(['apiroute.headers.include.endpoint_status' => false]);
    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    });

    $this->get('/api/v1/items')->assertOk()->assertHeaderMissing('X-API-Endpoint-Status')->assertHeader('Deprecation', Carbon::parse('2026-03-01')->format(Carbon::RFC7231));
});

test('headers.enabled=false disables endpoint headers too', function (): void {
    config(['apiroute.headers.enabled' => false]);
    ApiRoute::version('v1', function (): void {
        Route::get('items', [DeprecatedActionController::class, 'deprecated']);
    });

    $this->get('/api/v1/items')->assertOk()->assertHeaderMissing('Deprecation')->assertHeaderMissing('X-API-Endpoint-Status');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/EndpointHeadersTest.php`
Expected: FAIL — `X-API-Endpoint-Status` / `Deprecation` headers missing.

- [ ] **Step 3: Implement `EndpointHeaders`**

Create `src/Http/Headers/EndpointHeaders.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Headers;

use Carbon\Carbon;
use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds endpoint-level deprecation headers. Called after VersionHeaders so
 * that the more specific endpoint values replace the version ones.
 */
final class EndpointHeaders
{
    public function __construct(private readonly EndpointLifecycleResolver $resolver) {}

    public function addToResponse(Response $response, EndpointLifecycle $lifecycle): Response
    {
        /** @var array<string, mixed> $config */
        $config = config('apiroute.headers', []);

        if (($config['enabled'] ?? true) === false) {
            return $response;
        }

        /** @var array<string, bool> $include */
        $include = $config['include'] ?? [];

        if (($include['deprecation'] ?? true) && $lifecycle->deprecatedAt instanceof Carbon) {
            $response->headers->set('Deprecation', $lifecycle->deprecatedAt->format(Carbon::RFC7231));
        }

        if (($include['sunset'] ?? true) && $lifecycle->sunsetAt instanceof Carbon) {
            $response->headers->set('Sunset', $lifecycle->sunsetAt->format(Carbon::RFC7231));
        }

        if ($include['successor_link'] ?? true) {
            $links = [];
            $successorUrl = $this->resolver->resolveSuccessorUrl($lifecycle);

            if ($successorUrl !== null) {
                $links[] = "<{$successorUrl}>; rel=\"successor-version\"";
            }

            if ($lifecycle->docs !== null && $lifecycle->docs !== '') {
                $links[] = "<{$lifecycle->docs}>; rel=\"deprecation\"";
            }

            if ($links !== []) {
                $response->headers->set('Link', implode(', ', $links));
            }
        }

        if ($include['endpoint_status'] ?? true) {
            $response->headers->set('X-API-Endpoint-Status', $lifecycle->isSunset() ? 'sunset' : 'deprecated');
        }

        return $response;
    }
}
```

- [ ] **Step 4: Wire the listener, provider and config**

`src/Listeners/AddVersionHeadersToResponse.php`: inject `EndpointHeaders $endpointHeaders` and `EndpointLifecycleResolver $lifecycles` in the constructor (keep the existing three parameters), and change `handle()` so the endpoint headers are added **after** the version headers and **also when no version is resolved** (an endpoint can be deprecated outside a version group — keep it simple: run the endpoint block regardless):

```php
    public function handle(RequestHandled $event): void
    {
        $request = $event->request;
        $version = $this->resolveVersion($request);

        if ($version instanceof VersionDefinition) {
            $this->headers->addToResponse($event->response, $version, $request);
        }

        $lifecycle = $this->lifecycles->forRequest($request);

        if ($lifecycle instanceof EndpointLifecycle) {
            $this->endpointHeaders->addToResponse($event->response, $lifecycle);
        }
    }
```

`src/ApiRouteServiceProvider.php` `register()`: `$this->app->singleton(EndpointHeaders::class);` (import `Grazulex\ApiRoute\Http\Headers\EndpointHeaders`).

`config/apiroute.php`, inside `headers.include`, after `successor_link`:

```php
            'endpoint_status' => true,   // X-API-Endpoint-Status (deprecated|sunset)
```

- [ ] **Step 5: Run tests, PHPStan, Pint**

Run: `vendor/bin/pest tests/Feature/EndpointHeadersTest.php` — expected 7 passed.
Run: `vendor/bin/pest | tail -3` — all passed (the existing `HeadersTest`/`ConfigBasedHeadersTest` must still pass: they assert version headers on routes without attributes).
Run: `vendor/bin/phpstan analyse --no-progress && vendor/bin/pint --test`.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Headers/EndpointHeaders.php src/Listeners/AddVersionHeadersToResponse.php src/ApiRouteServiceProvider.php config/apiroute.php tests/Feature/EndpointHeadersTest.php
git commit -m "feat: endpoint-level Deprecation, Sunset and Link headers"
```

---

### Task 4: Endpoint sunset enforcement (410)

**Files:**
- Create: `src/Exceptions/EndpointSunsetException.php`, `src/Middleware/EnforceEndpointSunset.php`
- Modify: `src/ApiRouteManager.php` (`getMiddleware()`), `src/ApiRouteServiceProvider.php` (alias)
- Test: `tests/Feature/EndpointSunsetTest.php`

**Interfaces:**
- Consumes: `EndpointLifecycleResolver`, `EndpointLifecycle::isSunset()`.
- Produces: `EndpointSunsetException::__construct(EndpointLifecycle $lifecycle, ?string $successorUrl)` with `render(Request): JsonResponse`; middleware alias `api.endpoint-sunset` appended last to every version group.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/EndpointSunsetTest.php`:

```php
<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    EndpointLifecycleResolver::flush();
    Route::get('/api/v2/things', fn () => 'ok')->name('api.v2.things.index');
});

test('sunset endpoint is rejected with 410 and the json body', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $response = $this->get('/api/v1/things');

    $response->assertStatus(410)
        ->assertExactJson([
            'error' => 'endpoint_sunset',
            'message' => 'Use things v2',
            'sunset_at' => '2020-01-01T00:00:00+00:00',
            'successor' => 'http://localhost/api/v2/things',
            'docs' => 'https://docs.example.com/legacy',
        ])
        ->assertHeader('X-API-Endpoint-Status', 'sunset')
        ->assertHeader('X-API-Version', 'v1');
});

test('sunset status code follows config', function (): void {
    config(['apiroute.sunset.status_code' => 404]);
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $this->get('/api/v1/things')->assertStatus(404)->assertJson(['error' => 'endpoint_sunset']);
});

test('warn lets the request through with sunset headers', function (): void {
    config(['apiroute.sunset.action' => 'warn']);
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $this->get('/api/v1/things')->assertOk()->assertJson(['ok' => true])->assertHeader('X-API-Endpoint-Status', 'sunset')->assertHeader('Sunset');
});

test('allow lets the request through and still reports headers', function (): void {
    config(['apiroute.sunset.action' => 'allow']);
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
    });

    $this->get('/api/v1/things')->assertOk()->assertHeader('X-API-Endpoint-Status', 'sunset');
});

test('a deprecated but not yet sunset endpoint is served', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('legacy', [DeprecatedClassController::class, 'index']);
    });

    $this->get('/api/v1/legacy')->assertOk()->assertJson(['ok' => true]);
});

test('default message is used when no reason is given', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('gone', fn () => 'never')->deprecated(sunset: '2020-01-01');
    });

    $this->get('/api/v1/gone')->assertStatus(410)->assertJson(['message' => 'This endpoint is no longer available.', 'successor' => null, 'docs' => null]);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/EndpointSunsetTest.php`
Expected: FAIL — status 200 instead of 410.

- [ ] **Step 3: Implement exception and middleware**

Create `src/Exceptions/EndpointSunsetException.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Exceptions;

use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EndpointSunsetException extends ApiRouteException
{
    public const DEFAULT_MESSAGE = 'This endpoint is no longer available.';

    public function __construct(
        public readonly EndpointLifecycle $lifecycle,
        public readonly ?string $successorUrl,
    ) {
        parent::__construct($lifecycle->reason ?? self::DEFAULT_MESSAGE);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => 'endpoint_sunset',
            'message' => $this->getMessage(),
            'sunset_at' => $this->lifecycle->sunsetAt?->toIso8601String(),
            'successor' => $this->successorUrl,
            'docs' => $this->lifecycle->docs,
        ], $this->statusCode());
    }

    protected function statusCode(): int
    {
        return (int) config('apiroute.sunset.status_code', 410);
    }
}
```

Create `src/Middleware/EnforceEndpointSunset.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Grazulex\ApiRoute\Exceptions\EndpointSunsetException;
use Grazulex\ApiRoute\Support\EndpointLifecycle;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceEndpointSunset
{
    public function __construct(private readonly EndpointLifecycleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $lifecycle = $this->resolver->forRequest($request);

        if ($lifecycle instanceof EndpointLifecycle
            && $lifecycle->isSunset()
            && config('apiroute.sunset.action', 'reject') === 'reject') {
            throw new EndpointSunsetException($lifecycle, $this->resolver->resolveSuccessorUrl($lifecycle));
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Wire alias and version groups**

`src/ApiRouteServiceProvider.php` `registerMiddleware()`: add `$router->aliasMiddleware('api.endpoint-sunset', EnforceEndpointSunset::class);` (import the class).

`src/ApiRouteManager.php` `getMiddleware()`: right before `return $middleware;`, append:

```php
        // Endpoint-level sunset enforcement runs last, after version resolution
        $middleware[] = 'api.endpoint-sunset';
```

- [ ] **Step 5: Run tests, PHPStan, Pint**

Run: `vendor/bin/pest tests/Feature/EndpointSunsetTest.php` — expected 6 passed. If the ISO string differs by timezone (`+00:00` vs `Z`), align the expectation with `Carbon::parse('2020-01-01')->toIso8601String()` computed in the test rather than a literal.
Run: `vendor/bin/pest | tail -3` — all passed. `SunsetTest` (version-level) must be unchanged.
Run: `vendor/bin/phpstan analyse --no-progress && vendor/bin/pint --test`.

- [ ] **Step 6: Commit**

```bash
git add src/Exceptions/EndpointSunsetException.php src/Middleware/EnforceEndpointSunset.php src/ApiRouteManager.php src/ApiRouteServiceProvider.php tests/Feature/EndpointSunsetTest.php
git commit -m "feat: reject sunset endpoints with the configured sunset policy"
```

---

### Task 5: JSON:API negotiation and error documents

**Files:**
- Create: `src/Http/JsonApi.php`, `src/Http/Responses/JsonApiErrorDocument.php`
- Modify: `src/Exceptions/VersionNotFoundException.php`, `src/Exceptions/VersionSunsetException.php`, `src/Exceptions/InvalidVersionException.php`, `src/Exceptions/EndpointSunsetException.php`
- Test: `tests/Unit/JsonApiTest.php`, `tests/Feature/JsonApiErrorsTest.php`

**Interfaces:**
- Produces:
  - `final class JsonApi { public const MEDIA_TYPE = 'application/vnd.api+json'; public static function wanted(Request $request): bool; }`
  - `final class JsonApiErrorDocument { public static function make(int $status, string $code, string $title, ?string $detail = null, array $links = [], array $meta = []): JsonResponse; }`

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/JsonApiTest.php`:

```php
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
```

- [ ] **Step 2: Write the failing feature tests**

Create `tests/Feature/JsonApiErrorsTest.php`:

```php
<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Support\Facades\Route;

const JSON_API = ['Accept' => 'application/vnd.api+json'];

beforeEach(function (): void {
    EndpointLifecycleResolver::flush();
    Route::get('/api/v2/things', fn () => 'ok')->name('api.v2.things.index');
});

test('unknown version is a JSON:API error document when negotiated', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('test', fn () => 'ok');
    });

    $this->get('/api/v9/test', JSON_API)
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
    ApiRoute::version('v1', function (): void {
        Route::get('test', fn () => 'ok');
    });

    $response = $this->get('/api/v9/test');

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
    $exception = new \Grazulex\ApiRoute\Exceptions\InvalidVersionException('v-bad', 'Must match v{n}.');

    $plain = $exception->render(\Illuminate\Http\Request::create('/api'));
    $jsonApi = $exception->render(\Illuminate\Http\Request::create('/api', 'GET', server: ['HTTP_ACCEPT' => 'application/vnd.api+json']));

    expect($plain->getStatusCode())->toBe(400)
        ->and($plain->getData(true))->toBe(['error' => 'invalid_version', 'message' => "Invalid API version 'v-bad'. Must match v{n}."])
        ->and($jsonApi->getData(true)['errors'][0])->toMatchArray(['status' => '400', 'code' => 'invalid_version', 'title' => 'Invalid API version']);
});
```

- [ ] **Step 3: Run to verify failure**

Run: `vendor/bin/pest tests/Unit/JsonApiTest.php tests/Feature/JsonApiErrorsTest.php`
Expected: FAIL — classes not found; legacy-body test passes already (it documents the current body).

- [ ] **Step 4: Implement negotiation and the document builder**

Create `src/Http/JsonApi.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http;

use Illuminate\Http\Request;

final class JsonApi
{
    public const MEDIA_TYPE = 'application/vnd.api+json';

    /**
     * True when the client speaks JSON:API (Accept or Content-Type).
     */
    public static function wanted(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');
        $contentType = (string) $request->headers->get('Content-Type', '');

        return str_contains($accept, self::MEDIA_TYPE) || str_starts_with($contentType, self::MEDIA_TYPE);
    }
}
```

Create `src/Http/Responses/JsonApiErrorDocument.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Http\Responses;

use Grazulex\ApiRoute\Http\JsonApi;
use Illuminate\Http\JsonResponse;

/**
 * Builds a single-error JSON:API document (https://jsonapi.org/format/#errors).
 */
final class JsonApiErrorDocument
{
    /**
     * @param  array<string, string|null>  $links
     * @param  array<string, mixed>  $meta
     */
    public static function make(int $status, string $code, string $title, ?string $detail = null, array $links = [], array $meta = []): JsonResponse
    {
        $error = ['status' => (string) $status, 'code' => $code, 'title' => $title];

        if ($detail !== null && $detail !== '') {
            $error['detail'] = $detail;
        }

        $links = array_filter($links, fn (?string $link): bool => $link !== null && $link !== '');
        if ($links !== []) {
            $error['links'] = $links;
        }

        $meta = array_filter($meta, fn (mixed $value): bool => $value !== null);
        if ($meta !== []) {
            $error['meta'] = $meta;
        }

        return new JsonResponse(['errors' => [$error]], $status, ['Content-Type' => JsonApi::MEDIA_TYPE]);
    }
}
```

- [ ] **Step 5: Branch the exceptions**

`VersionNotFoundException::render()` becomes:

```php
    public function render(Request $request): JsonResponse
    {
        $available = ApiRoute::versions()->pluck('name')->toArray();

        if (JsonApi::wanted($request)) {
            return JsonApiErrorDocument::make(404, 'version_not_found', 'API version not found', $this->getMessage(), [], [
                'requested_version' => $this->requestedVersion,
                'available_versions' => $available,
            ]);
        }

        return response()->json([
            'error' => 'version_not_found',
            'message' => $this->getMessage(),
            'requested_version' => $this->requestedVersion,
            'available_versions' => $available,
        ], 404);
    }
```

`VersionSunsetException::render()` — keep the legacy array exactly as today and add the branch before it:

```php
        if (JsonApi::wanted($request)) {
            return JsonApiErrorDocument::make((int) ($config['sunset']['status_code'] ?? 410), 'version_sunset', 'API version sunset', "API version {$this->version->name()} is no longer available.", [
                'successor' => $this->version->successor(),
                'about' => $migrationGuides[$this->version->name()] ?? null,
            ], [
                'sunset_date' => $this->version->sunsetDate()?->toIso8601String(),
                'successor' => $this->version->successor(),
                'migration_guide' => $migrationGuides[$this->version->name()] ?? null,
            ]);
        }
```

`InvalidVersionException` — add `use Illuminate\Http\JsonResponse; use Illuminate\Http\Request;` and:

```php
    public function render(Request $request): JsonResponse
    {
        if (JsonApi::wanted($request)) {
            return JsonApiErrorDocument::make(400, 'invalid_version', 'Invalid API version', $this->getMessage());
        }

        return response()->json(['error' => 'invalid_version', 'message' => $this->getMessage()], 400);
    }
```

`EndpointSunsetException::render()` — add the branch at the top:

```php
        if (JsonApi::wanted($request)) {
            return JsonApiErrorDocument::make($this->statusCode(), 'endpoint_sunset', 'Endpoint sunset', $this->getMessage(), [
                'about' => $this->lifecycle->docs,
                'successor' => $this->successorUrl,
            ], [
                'sunset_at' => $this->lifecycle->sunsetAt?->toIso8601String(),
            ]);
        }
```

Import `Grazulex\ApiRoute\Http\JsonApi` and `Grazulex\ApiRoute\Http\Responses\JsonApiErrorDocument` in each exception.

- [ ] **Step 6: Run tests, PHPStan, Pint**

Run: `vendor/bin/pest tests/Unit/JsonApiTest.php tests/Feature/JsonApiErrorsTest.php` — expected 10 passed.
Run: `vendor/bin/pest | tail -3` — all passed.
Run: `vendor/bin/phpstan analyse --no-progress && vendor/bin/pint --test`.

- [ ] **Step 7: Commit**

```bash
git add src/Http/JsonApi.php src/Http/Responses/JsonApiErrorDocument.php src/Exceptions tests/Unit/JsonApiTest.php tests/Feature/JsonApiErrorsTest.php
git commit -m "feat: JSON:API error documents by content negotiation"
```

---

### Task 6: `InteractsWithApiVersion` trait for Laravel 13 JSON:API resources

**Files:**
- Create: `src/Http/Resources/InteractsWithApiVersion.php`
- Test: `tests/Feature/JsonApiResourceTraitTest.php`, `tests/Support/Resources/ThingResource.php`

**Interfaces:**
- Consumes: `ApiVersionContext::getVersion()`, `EndpointLifecycleResolver::forRequest()/resolveSuccessorUrl()`.
- Produces: `trait InteractsWithApiVersion { public function with($request): array; protected function apiVersionDocumentMembers(Request $request): array; }`.

- [ ] **Step 1: Create the test resource (guarded)**

`tests/Support/Resources/ThingResource.php`:

```php
<?php

declare(strict_types=1);

namespace Grazulex\ApiRoute\Tests\Support\Resources;

use Grazulex\ApiRoute\Http\Resources\InteractsWithApiVersion;
use Illuminate\Http\Request;

if (class_exists(\Illuminate\Http\Resources\JsonApi\JsonApiResource::class)) {
    final class ThingResource extends \Illuminate\Http\Resources\JsonApi\JsonApiResource
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
```

Before writing it, open `vendor/laravel/framework/src/Illuminate/Http/Resources/JsonApi/JsonApiResource.php` (present on Laravel 13 checkouts) and copy the exact signatures of `toType`, `toId`, `toAttributes` (return types may be `string|int` / `array`), plus how `resolve()` expects the resource; adjust the class above to match so it produces a valid document.

- [ ] **Step 2: Write the failing feature tests**

Create `tests/Feature/JsonApiResourceTraitTest.php`:

```php
<?php

declare(strict_types=1);

use Carbon\Carbon;
use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedActionController;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    if (! class_exists(\Illuminate\Http\Resources\JsonApi\JsonApiResource::class)) {
        $this->markTestSkipped('JSON:API resources require Laravel 13.');
    }
    EndpointLifecycleResolver::flush();
});

test('adds version meta to a JSON:API resource document', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('things/1', fn () => new \Grazulex\ApiRoute\Tests\Support\Resources\ThingResource(['id' => 1, 'name' => 'One']));
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
        Route::get('items', fn () => new \Grazulex\ApiRoute\Tests\Support\Resources\ThingResource(['id' => 2, 'name' => 'Two']))
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
        Route::get('things/3', fn () => new \Grazulex\ApiRoute\Tests\Support\Resources\ThingResource(['id' => 3, 'name' => 'Three']));
    });

    $response = $this->get('/api/v1/things/3', ['Accept' => 'application/vnd.api+json'])->assertOk();

    expect($response->json('meta.api'))->toBe(['version' => 'v1', 'status' => 'active'])
        ->and($response->json('links'))->toBeNull();
});
```

- [ ] **Step 3: Run to verify failure (on Laravel 13)**

Run: `composer show laravel/framework | grep versions` — if it is 12.x, temporarily switch: `cp composer.json /tmp/apiroute-composer.bak && composer require "laravel/framework:13.*" "orchestra/testbench:11.*" --no-update --no-interaction && composer update --no-interaction`; restore `composer.json` from the backup at the end of the task and run `composer update` again (`git diff composer.json` must be empty afterwards).
Run: `vendor/bin/pest tests/Feature/JsonApiResourceTraitTest.php` — expected FAIL, trait not found.

- [ ] **Step 4: Implement the trait**

Create `src/Http/Resources/InteractsWithApiVersion.php`:

```php
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
```

- [ ] **Step 5: Run tests on Laravel 13 and Laravel 12**

Run: `vendor/bin/pest tests/Feature/JsonApiResourceTraitTest.php` — expected 3 passed on L13.
Switch to L12 (`composer require "laravel/framework:12.*" "orchestra/testbench:10.*" --no-update --no-interaction && composer update --no-interaction`) and run the same file — expected 3 skipped; run `vendor/bin/pest | tail -3` — all passed. Restore `composer.json` (`git diff composer.json` empty) and `composer update`.
Run: `vendor/bin/phpstan analyse --no-progress` — if PHPStan cannot resolve `parent::with()` inside the trait, add `@phpstan-ignore-next-line` with the reason "trait is applied to JsonApiResource subclasses"; `vendor/bin/pint --test`.

- [ ] **Step 6: Commit**

```bash
git add src/Http/Resources/InteractsWithApiVersion.php tests/Support/Resources/ThingResource.php tests/Feature/JsonApiResourceTraitTest.php
git commit -m "feat: InteractsWithApiVersion trait for Laravel 13 JSON:API resources"
```

---

### Task 7: Deprecated endpoints in `api:status`

**Files:**
- Modify: `src/Commands/ApiStatusCommand.php`
- Test: `tests/Feature/ApiStatusDeprecatedEndpointsTest.php`

**Interfaces:**
- Consumes: `EndpointLifecycleResolver::forRoute()`, `resolveSuccessorUrl()`.
- Produces: table "Deprecated endpoints" after the versions table; `--json` output becomes `{"versions": [...], "deprecated_endpoints": [...]}` **only when at least one deprecated endpoint exists**; otherwise the JSON output stays the current flat array (backward compatible).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ApiStatusDeprecatedEndpointsTest.php`:

```php
<?php

declare(strict_types=1);

use Grazulex\ApiRoute\Facades\ApiRoute;
use Grazulex\ApiRoute\Support\EndpointLifecycleResolver;
use Grazulex\ApiRoute\Tests\Support\Controllers\DeprecatedClassController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => EndpointLifecycleResolver::flush());

test('lists deprecated endpoints in the table output', function (): void {
    Route::get('/api/v2/things', fn () => 'ok')->name('api.v2.things.index');
    ApiRoute::version('v1', function (): void {
        Route::get('things', [DeprecatedClassController::class, 'sunset']);
        Route::get('fresh', fn () => 'ok');
    });

    $this->artisan('api:status')
        ->expectsOutputToContain('Deprecated endpoints')
        ->expectsOutputToContain('api/v1/things')
        ->expectsOutputToContain('2020-01-01')
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

    expect($json['versions'][0]['version'])->toBe('v1')
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

test('json output stays a flat array without deprecated endpoints', function (): void {
    ApiRoute::version('v1', function (): void {
        Route::get('fresh', fn () => 'ok');
    });

    Artisan::call('api:status', ['--json' => true]);

    expect(json_decode(Artisan::output(), true))->toBeList();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/pest tests/Feature/ApiStatusDeprecatedEndpointsTest.php` — expected FAIL.

- [ ] **Step 3: Implement**

In `ApiStatusCommand`:
- Inject `EndpointLifecycleResolver $resolver` into `handle()` (Laravel resolves it) and pass it to `showAllVersions()`.
- Add a private method:

```php
    /**
     * @return list<array{method: string, uri: string, version: string, since: string|null, sunset: string|null, successor: string|null, is_sunset: bool}>
     */
    private function deprecatedEndpoints(ApiRouteManager $manager, EndpointLifecycleResolver $resolver): array
    {
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            $lifecycle = $resolver->forRoute($route);
            if (! $lifecycle instanceof EndpointLifecycle) {
                continue;
            }

            $rows[] = [
                'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'version' => $this->versionOfRoute($route, $manager),
                'since' => $lifecycle->deprecatedAt?->format('Y-m-d'),
                'sunset' => $lifecycle->sunsetAt?->format('Y-m-d'),
                'successor' => $resolver->resolveSuccessorUrl($lifecycle),
                'is_sunset' => $lifecycle->isSunset(),
            ];
        }

        return $rows;
    }

    private function versionOfRoute(\Illuminate\Routing\Route $route, ApiRouteManager $manager): string
    {
        foreach ($manager->versions() as $version) {
            if (preg_match('#(^|/)'.preg_quote($version->name(), '#').'(/|$)#', $route->uri()) === 1) {
                return $version->name();
            }
        }

        return '-';
    }
```

- In `showAllVersions()`: compute `$endpoints = $this->deprecatedEndpoints($manager, $resolver);`. For `--json`: output `json_encode($endpoints === [] ? $rows : ['versions' => $rows, 'deprecated_endpoints' => $endpoints], JSON_PRETTY_PRINT)`. For the table output: after the versions table and warnings, if `$endpoints !== []`:

```php
            $this->newLine();
            $this->info('Deprecated endpoints');
            $this->table(
                ['Method', 'URI', 'Version', 'Since', 'Sunset', 'Successor'],
                array_map(fn (array $row): array => [
                    $row['method'],
                    $row['uri'],
                    $row['version'],
                    $row['since'] ?? '-',
                    ($row['sunset'] ?? '-').($row['is_sunset'] ? ' (SUNSET)' : ''),
                    $row['successor'] ?? '-',
                ], $endpoints),
            );
```

Imports: `Grazulex\ApiRoute\Support\EndpointLifecycle`, `Grazulex\ApiRoute\Support\EndpointLifecycleResolver`, `Illuminate\Support\Facades\Route`.

- [ ] **Step 4: Run tests, PHPStan, Pint**

Run: `vendor/bin/pest tests/Feature/ApiStatusDeprecatedEndpointsTest.php` — expected 3 passed. Existing `api:status` tests (grep `api:status` in `tests/`) must still pass.
Run: `vendor/bin/pest | tail -3`; `vendor/bin/phpstan analyse --no-progress && vendor/bin/pint --test`.

- [ ] **Step 5: Commit**

```bash
git add src/Commands/ApiStatusCommand.php tests/Feature/ApiStatusDeprecatedEndpointsTest.php
git commit -m "feat: list deprecated endpoints in api:status"
```

---

### Task 8: Documentation, changelog, PR and release

**Files:**
- Modify: `README.md`, `CHANGELOG.md`

- [ ] **Step 1: README**

Add two bullets to `## Features`: `- **Endpoint deprecation** - \`#[Deprecated]\` attribute or \`->deprecated()\` route macro with RFC 8594 / RFC 9745 headers and 410 sunset policy` and `- **JSON:API** - error documents by content negotiation, version metadata for Laravel 13 JSON:API resources`.

Add a section `## Deprecating a single endpoint` after `## Automatic Headers` with: the attribute example (class + method, `use Grazulex\ApiRoute\Attributes\Deprecated;`), the macro example on a closure, the headers table (`Deprecation`, `Sunset`, `Link` successor-version / deprecation, `X-API-Endpoint-Status`), the sentence "Method attributes override class attributes field by field; the macro overrides attributes", the `successor` resolution rules (named route / path / URL), the sunset policy (`apiroute.sunset.action`, same as versions, status code from `apiroute.sunset.status_code`), a note about PHP 8.4's native `#[\Deprecated]` cohabitation, and `api:status` output.

Add a section `## JSON:API` with: negotiation rule, one example error document (the 410 endpoint one), the list of codes (`version_not_found`, `invalid_version`, `version_sunset`, `endpoint_sunset`), and the trait example for Laravel 13:

```php
use Grazulex\ApiRoute\Http\Resources\InteractsWithApiVersion;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    use InteractsWithApiVersion;
    // ...
}
```
with the resulting `meta.api` / `links.successor` shape.

Update the configuration snippet in the README to show `'endpoint_status' => true`.

- [ ] **Step 2: CHANGELOG**

Insert at the top of `CHANGELOG.md`, after the intro lines, following the file's existing style:

```markdown
## [2.2.0](https://github.com/Grazulex/laravel-apiroute/releases/tag/v2.2.0) (<release date YYYY-MM-DD>)

### Features

- endpoint-level deprecation with the `#[Deprecated]` attribute (class or action) and the `->deprecated()` route macro: `Deprecation`, `Sunset`, `Link` (successor-version, deprecation) and `X-API-Endpoint-Status` headers
- sunset endpoints are rejected (410 by default) following the existing `apiroute.sunset` policy (`api.endpoint-sunset` middleware)
- JSON:API error documents for version and endpoint errors when the client sends `Accept: application/vnd.api+json`
- `InteractsWithApiVersion` trait adding `meta.api` and `links.successor` to Laravel 13 JSON:API resources
- `api:status` lists deprecated endpoints (table and `--json`)
- new config key `headers.include.endpoint_status`
```

- [ ] **Step 3: Full verification on both Laravel versions**

```bash
vendor/bin/pint --test && vendor/bin/phpstan analyse --no-progress && vendor/bin/pest | tail -3
cp composer.json /tmp/apiroute-composer.bak
composer require "laravel/framework:12.*" "orchestra/testbench:10.*" --no-update --no-interaction && composer update --no-interaction && vendor/bin/pest | tail -3
cp /tmp/apiroute-composer.bak composer.json && rm /tmp/apiroute-composer.bak && composer update --no-interaction
git diff --stat composer.json   # must be empty
```
Expected: all green on L13 (trait tests run) and on L12 (trait tests skipped).

- [ ] **Step 4: Commit, PR, merge, tag**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: endpoint deprecation attributes and JSON:API support"
git push -u origin feature/endpoint-lifecycle
gh pr create --title "feat: endpoint deprecation attributes and JSON:API support" --body "Adds #[Deprecated] / ->deprecated() endpoint lifecycle with RFC headers and 410 policy, JSON:API error documents by content negotiation, and an InteractsWithApiVersion trait for Laravel 13 JSON:API resources. See docs/superpowers/specs/2026-09-17-apiroute-endpoint-lifecycle-jsonapi-design.md."
gh pr checks --watch
```
When all checks are green: `gh pr merge --merge --delete-branch`, `git checkout main && git pull --ff-only`, then **only after the merge**: `git tag -a v2.2.0 -m "v2.2.0" && git push origin v2.2.0`, and `gh release create v2.2.0 --title "v2.2.0" --notes "<the 2.2.0 changelog section>"`. Never move or delete a pushed tag; if something is wrong after tagging, publish v2.2.1.

---

## Self-review

- **Spec coverage**: §3.1 attribute → T1; §3.2 value → T1; §3.3 macro → T2; §3.4 resolver (memo, successor rules, unknown route behaviour) → T2; §4.1 headers incl. `endpoint_status` config → T3; §4.2 middleware/exception/policy → T4; §4.3 `api:status` → T7; §5.1 negotiation → T5; §5.2 documents + 4 exceptions → T5; §5.3 trait → T6; §6 config/README/CHANGELOG/tests → T3, T8; regression byte-identical → T5 legacy-body test.
- **Placeholders**: none. `<release date>` in the changelog is filled by the executor with the actual date at Task 8.
- **Type consistency**: `EndpointLifecycle` field names (`deprecatedAt`, `sunsetAt`, `successor`, `docs`, `reason`) identical in T1–T7; `EndpointLifecycleResolver::forRoute/forRequest/resolveSuccessorUrl/flush` identical in T2–T7; `EndpointSunsetException::statusCode()` used by both render branches in T4/T5; config key `headers.include.endpoint_status` in T3 and README (T8).
