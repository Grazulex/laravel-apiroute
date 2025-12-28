# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0](https://github.com/Grazulex/laravel-apiroute/releases/tag/v1.1.0) (2025-12-28)

### Bug Fixes

- resolve serialization error when tracking API usage ([2df297e](https://github.com/Grazulex/laravel-apiroute/commit/2df297e5f60119995a8f31496e96eb257accc23d))
## [1.0.0](https://github.com/Grazulex/laravel-apiroute/releases/tag/v1.0.0) (2025-12-25)

### Chores

- clean up development-only files for production ([c9f378a](https://github.com/Grazulex/laravel-apiroute/commit/c9f378ad71027e40b0e6a662d938be3f492846a7))
## [0.0.3](https://github.com/Grazulex/laravel-apiroute/releases/tag/v0.0.3) (2025-12-23)

### Bug Fixes

- improve JSON output and Link header generation ([c2b05c9](https://github.com/Grazulex/laravel-apiroute/commit/c2b05c95cb1ce1fbbeacc43fb4d0a6548feaceae))

### Documentation

- clean up CHANGELOG format ([c5138d8](https://github.com/Grazulex/laravel-apiroute/commit/c5138d86e546a523cf71a29ecff8e7c2732cfbd4))
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
