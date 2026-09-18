# Laravel ApiRoute

[![Latest Version on Packagist](https://img.shields.io/packagist/v/grazulex/laravel-apiroute.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-apiroute)
[![Tests](https://github.com/grazulex/laravel-apiroute/actions/workflows/tests.yml/badge.svg)](https://github.com/grazulex/laravel-apiroute/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/grazulex/laravel-apiroute/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/grazulex/laravel-apiroute/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/grazulex/laravel-apiroute/actions/workflows/code-style.yml/badge.svg)](https://github.com/grazulex/laravel-apiroute/actions/workflows/code-style.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-apiroute.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-apiroute)
[![License](https://img.shields.io/packagist/l/grazulex/laravel-apiroute.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-apiroute)

> Complete API versioning lifecycle management for Laravel

## Features

- **Multi-strategy versioning** - URI path, Header, Query parameter, or Accept header
- **Automatic deprecation headers** - `Deprecation` (RFC 9745) and `Sunset` (RFC 8594) headers as RFC 7231 HTTP-dates
- **Version lifecycle management** - Active, Deprecated, Sunset, Removed states
- **Intelligent fallback** - Route fallback to previous versions when needed
- **Artisan commands** - Scaffold, monitor, and manage API versions
- **Usage tracking** - Optional analytics per API version
- **Zero configuration start** - Works out of the box with sensible defaults
- **Endpoint deprecation** - `#[Deprecated]` attribute or `->deprecated()` route macro with `Deprecation` (RFC 9745) and `Sunset` (RFC 8594) headers and 410 sunset policy
- **JSON:API** - error documents by content negotiation, version metadata for Laravel 13 JSON:API resources

## Requirements

- PHP 8.3+
- Laravel 12.x or 13.x

## Installation

```bash
composer require grazulex/laravel-apiroute
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="apiroute-config"
```

## Documentation

For complete documentation including migrations, advanced configuration, and usage tracking setup, please visit the **[Wiki](https://github.com/Grazulex/laravel-apiroute/wiki)**.

## Quick Start

### 1. Define versions in config

```php
// config/apiroute.php

'versions' => [
    'v1' => [
        'routes' => base_path('routes/api/v1.php'),
        'status' => 'deprecated',
        'deprecated_at' => '2025-06-01',
        'sunset_at' => '2025-12-01',
        'successor' => 'v2',
    ],
    'v2' => [
        'routes' => base_path('routes/api/v2.php'),
        'status' => 'active',
    ],
    'v3' => [
        'routes' => base_path('routes/api/v3.php'),
        'status' => 'beta',
    ],
],
```

### 2. Create route files

```php
// routes/api/v2.php
use Illuminate\Support\Facades\Route;

Route::apiResource('users', App\Http\Controllers\Api\V2\UserController::class);
```

## Versioning Strategies

### URI Path (Default)

```
GET /api/v1/users
GET /api/v2/users
```

### Header

```
GET /api/users
X-API-Version: 2
```

### Query Parameter

```
GET /api/users?api_version=2
```

### Accept Header

```
GET /api/users
Accept: application/vnd.api.v2+json
```

### Subdomain Routing

For APIs served from a dedicated subdomain:

```php
// config/apiroute.php
'strategies' => [
    'uri' => [
        'prefix' => '',                    // No /api prefix
        'domain' => 'api.example.com',     // Your API subdomain
    ],
],
```

```
GET https://api.example.com/v1/users
GET https://api.example.com/v2/users
```

### Multi-Domain Routing

For resilience or redundancy scenarios where the same API is served on multiple domains:

```php
// config/apiroute.php
'strategies' => [
    'uri' => [
        'prefix' => '',
        'domain' => ['api.main.com', 'api.backup.com', 'api.proxy.com'],
    ],
],
```

All domains resolve to the same versioned routes:

```
GET https://api.main.com/v1/users
GET https://api.backup.com/v1/users
GET https://api.proxy.com/v1/users
```

Use environment variables for flexible configuration:

```php
'domain' => array_filter(array_map('trim', explode(',', env('API_DOMAINS', '')))),
```

```env
# .env
API_DOMAINS=api.main.com,api.backup.com,api.proxy.com
```

> **Route names:** when named routes (`->name(...)`, or the version's `name` prefix) are registered on more than one domain, only the **first** domain in the list keeps the exact configured name — so `route('api.users')` stays backward compatible. Every additional domain automatically gets a unique, domain-derived suffix (e.g. `api.api_backup_com.users`) so `php artisan route:cache` doesn't fail with duplicate route name errors.

## Automatic Headers

On deprecated versions, responses include RFC-compliant headers:

```http
HTTP/1.1 200 OK
Deprecation: Sun, 01 Jun 2025 00:00:00 GMT
Sunset: Mon, 01 Dec 2025 00:00:00 GMT
Link: </api/v2/users>; rel="successor-version"
X-API-Version: v1
X-API-Version-Status: deprecated
```

## Deprecating a single endpoint

Beyond version-level deprecation, a single controller class, action, or route can be marked deprecated on its own, independently of the API version it belongs to.

### Attribute

```php
use Grazulex\ApiRoute\Attributes\Deprecated;

#[Deprecated(since: '2026-01-01', successor: '/api/v2/legacy', docs: 'https://docs.example.com/legacy')]
class LegacyController
{
    #[Deprecated(sunset: '2026-06-01', successor: 'api.v2.things.index', reason: 'Use things v2')]
    public function index()
    {
        // ...
    }
}
```

Method attributes override class attributes field by field; the macro overrides attributes.

### Route macro

```php
Route::get('/api/v2/closure', fn () => response()->json(['ok' => true]))
    ->deprecated(since: '2026-05-05', successor: '/api/v2/closure-v2');
```

### Headers

| Header | When | Notes |
| --- | --- | --- |
| `Deprecation` | `since` is set | RFC 9745 header, emitted as an RFC 7231 HTTP-date (same format as the version headers) |
| `Sunset` | `sunset` is set | RFC 8594 header, RFC 7231 HTTP-date |
| `Link` | `successor` and/or `docs` set | `rel="successor-version"` and `rel="deprecation"` (RFC 9745) |
| `X-API-Endpoint-Status` | endpoint is deprecated or sunset | `deprecated` or `sunset`, gated by `headers.include.endpoint_status` |

`successor` is resolved in this order: a named route, a path starting with `/`, or an absolute URL. A named route is generated with the parameters of the current route (`things/{id}` can point to `api.v2.things.show`); when the URL cannot be generated, a warning is logged and no successor link is emitted.

### Sunset policy

Once `sunset` is reached, the endpoint is handled by the `api.endpoint-sunset` middleware following the same policy as versions: `apiroute.sunset.action` (`reject` by default) and the status code from `apiroute.sunset.status_code` (410 by default).

This middleware is added automatically to routes registered inside `ApiRoute::version()` groups. Outside those groups, a deprecated route only gets the headers above; add `api.endpoint-sunset` to the route or group yourself to apply the 410 policy there.

PHP 8.4's native `#[\Deprecated]` attribute can be used alongside `Grazulex\ApiRoute\Attributes\Deprecated` on the same class or method; they serve different purposes (IDE/runtime deprecation notice vs. HTTP lifecycle) and do not conflict.

`php artisan api:status` lists deprecated endpoints (method, URI, version, dates, successor) in a dedicated table. With `--json`, the output keeps its historical shape (an object keyed by version); when deprecated endpoints exist, it becomes `{"versions": {...}, "deprecated_endpoints": [...]}`.

## JSON:API

When a request sends `Accept: application/vnd.api+json`, version and endpoint errors are rendered as [JSON:API error documents](https://jsonapi.org/format/#errors) instead of the plain JSON body.

```http
GET /api/v1/things
Accept: application/vnd.api+json
```

```json
{
    "errors": [
        {
            "status": "410",
            "code": "endpoint_sunset",
            "title": "Endpoint sunset",
            "detail": "Use things v2",
            "links": {
                "about": "https://docs.example.com/legacy",
                "successor": "http://localhost/api/v2/things"
            },
            "meta": {
                "sunset_at": "2020-01-01T00:00:00+00:00"
            }
        }
    ]
}
```

The error `code` is one of: `version_not_found`, `invalid_version`, `version_sunset`, `endpoint_sunset`.

### Laravel 13 JSON:API resources

The `InteractsWithApiVersion` trait adds version metadata to a `JsonApiResource` document:

```php
use Grazulex\ApiRoute\Http\Resources\InteractsWithApiVersion;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    use InteractsWithApiVersion;
    // ...
}
```

It merges a `meta.api` object (`version`, `status`, `deprecation`, `sunset`, `successor`) and, when a successor is resolvable, a top-level `links.successor` into the resource document. Endpoint-level deprecation takes precedence over version-level deprecation.

## Artisan Commands

```bash
# View status of all API versions
php artisan api:status

# Create a new API version
php artisan api:version v3 --copy-from=v2

# Mark a version as deprecated
php artisan api:deprecate v1 --on=2025-06-01 --sunset=2025-12-01

# View usage statistics
php artisan api:stats --period=30
```

## Configuration

```php
// config/apiroute.php

return [
    // API versions (v2.0+)
    'versions' => [
        'v1' => [
            'routes' => base_path('routes/api/v1.php'),
            'middleware' => [],
            'status' => 'active',  // 'active', 'beta', 'deprecated', 'sunset'
            'deprecated_at' => null,
            'sunset_at' => null,
            'successor' => null,
            'documentation' => null,
            'rate_limit' => null,
        ],
    ],

    // Detection strategy: 'uri', 'header', 'query', 'accept'
    'strategy' => 'uri',

    // Default version when none specified
    'default_version' => 'latest',

    // Fallback behavior
    'fallback' => [
        'enabled' => true,
        'strategy' => 'previous',
    ],

    // Sunset behavior: 'reject', 'warn', 'allow'
    'sunset' => [
        'action' => 'reject',
        'status_code' => 410,
    ],

    // Response headers
    'headers' => [
        'enabled' => true,
        'include' => [
            'version' => true,
            'deprecation' => true,
            'sunset' => true,
            'endpoint_status' => true,
        ],
    ],
];
```

## Testing

```bash
composer test
```

## Code Quality

```bash
# Run all quality checks
composer full

# Individual checks
composer test:lint   # Laravel Pint
composer test:types  # PHPStan
composer test:unit   # Pest
```

## Changelog

Please see [RELEASES](RELEASES.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Jean-Marc Strauven](https://github.com/Grazulex)
- [All Contributors](../../contributors)

## Thanks

- [@maks-oleksyuk](https://github.com/maks-oleksyuk) - Bug reports and testing
- [@sameededitz](https://github.com/sameededitz) - Feature request for subdomain and multi-domain routing

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
