# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Fixed
- Migration file now published as `.php` instead of `.stub` (no manual rename required)
- `versions()->pluck('name')` now works correctly (added `__get()` magic method to `VersionDefinition`)

### Added
- Auto-registration of `TrackApiUsage` middleware when tracking is enabled in config
- Auto-registration of `FallbackRoute` middleware when fallback is enabled in config
- New `FallbackRoute` middleware that redirects to previous versions when a route doesn't exist in the requested version
- `api.fallback` middleware alias for manual middleware configuration

### Changed
- `ApiRouteManager::getMiddleware()` now dynamically builds middleware stack based on config
- Middleware stack now includes `api.fallback` by default (when `fallback.enabled` is true)
- Middleware stack now includes `api.track` when `tracking.enabled` is true

## [0.0.1](https://github.com/Grazulex/laravel-apiroute/releases/tag/v0.0.1) (2025-12-23)
