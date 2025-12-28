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

Define your API versions in `routes/api.php`:

```php
use Grazulex\ApiRoute\Facades\ApiRoute;

// Version 1 - Deprecated, sunset planned
ApiRoute::version('v1', function () {
    Route::apiResource('users', App\Http\Controllers\Api\V1\UserController::class);
})
->deprecated('2025-06-01')
->sunset('2025-12-01');

// Version 2 - Current stable version
ApiRoute::version('v2', function () {
    Route::apiResource('users', App\Http\Controllers\Api\V2\UserController::class);
})->current();

// Version 3 - Beta/Preview
ApiRoute::version('v3', function () {
    Route::apiResource('users', App\Http\Controllers\Api\V3\UserController::class);
})->beta();
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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
