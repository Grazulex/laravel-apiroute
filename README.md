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
- **Automatic deprecation headers** - RFC 8594 (Deprecation) and RFC 7231 (Sunset) compliant
- **Version lifecycle management** - Active, Deprecated, Sunset, Removed states
- **Intelligent fallback** - Route fallback to previous versions when needed
- **Artisan commands** - Scaffold, monitor, and manage API versions
- **Usage tracking** - Optional analytics per API version
- **Zero configuration start** - Works out of the box with sensible defaults

## Requirements

- PHP 8.3+
- Laravel 12.x

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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
