# Laravel ApiRoute - Instructions Claude Code

## Project Overview

**Package:** `grazulex/laravel-apiroute`
**Purpose:** Complete API versioning lifecycle management for Laravel
**Author:** Jean-Marc Strauven (@Grazulex)

## Tech Stack

- **PHP:** 8.3+
- **Laravel:** 12.x
- **Testing:** Pest PHP 3.x
- **Static Analysis:** PHPStan (Larastan) level 8
- **Code Style:** Laravel Pint
- **Refactoring:** Rector

## Key Commands

```bash
# Run all tests
composer test

# Run individual checks
composer test:lint    # Pint code style
composer test:types   # PHPStan analysis
composer test:unit    # Pest tests

# Fix code style
composer lint
```

## Architecture

### Namespace: `Grazulex\ApiRoute`

```
src/
├── ApiRouteServiceProvider.php    # Main service provider
├── ApiRouteManager.php            # Central version management
├── VersionResolver.php            # Resolves version from request
├── VersionDefinition.php          # Fluent API for version config
├── Facades/ApiRoute.php           # Laravel facade
├── Middleware/                    # HTTP middleware
├── Commands/                      # Artisan commands
├── Events/                        # Domain events
├── Exceptions/                    # Custom exceptions
├── Contracts/                     # Interfaces
├── Support/                       # Enums, helpers
├── Http/Headers/                  # HTTP header handling
└── Tracking/                      # Usage tracking drivers
```

## Coding Standards

1. **Strict typing** - Use `declare(strict_types=1);` in all files
2. **PHPDoc** - Only when adding value beyond type hints
3. **Final classes** - Prefer `final` for non-extendable classes
4. **Readonly properties** - Use when appropriate
5. **Enums** - Use PHP 8.1+ enums for status values

## Testing Patterns

- Use **Pest PHP** syntax (not PHPUnit)
- Feature tests in `tests/Feature/`
- Unit tests in `tests/Unit/`
- Use Orchestra Testbench for Laravel integration

## Important Specs

- Implements RFC 8594 (Deprecation Header)
- Implements RFC 7231 (Sunset Header)
- Implements RFC 8288 (Link Header)

## References

- Full spec: `laravel-apiroute-spec.md`
- Backlog: `backlog/`
