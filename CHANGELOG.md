# Changelog

All notable changes to this project will be documented in this file.

## [0.0.2](https://github.com/Grazulex/laravel-apiroute/releases/tag/v0.0.2) (2025-12-23)

### Bug Fixes

- Migration file now published as `.php` instead of `.stub` (no manual rename required)
- `versions()->pluck('name')` now works correctly (added `__get()` magic method to `VersionDefinition`)

### Features

- Auto-registration of `TrackApiUsage` middleware when tracking is enabled in config
- Auto-registration of `FallbackRoute` middleware when fallback is enabled in config
- New `FallbackRoute` middleware that redirects to previous versions when a route doesn't exist in the requested version
- `api.fallback` middleware alias for manual middleware configuration

### Changes

- `ApiRouteManager::getMiddleware()` now dynamically builds middleware stack based on config
- Middleware stack now includes `api.fallback` by default (when `fallback.enabled` is true)
- Middleware stack now includes `api.track` when `tracking.enabled` is true

## [0.0.1](https://github.com/Grazulex/laravel-apiroute/releases/tag/v0.0.1) (2025-12-23)

### Initial Release

- Complete API versioning lifecycle management for Laravel
- Multi-strategy version detection (URI, Header, Query, Accept)
- RFC 8594/7231 compliant headers (Deprecation, Sunset)
- Version lifecycle management (active, beta, deprecated, sunset)
- Artisan commands (api:status, api:version, api:deprecate, api:sunset, api:stats)
- Optional usage tracking (database/redis)
- Rate limiting per version
- 53 passing tests with Pest
