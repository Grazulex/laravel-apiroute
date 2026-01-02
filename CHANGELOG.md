# Changelog

All notable changes to this project will be documented in this file.

## [2.0.1](https://github.com/Grazulex/laravel-apiroute/releases/tag/v2.0.1) (2026-01-02)

### Bug Fixes

- apply custom middleware and route name prefix from config ([5382d6d](https://github.com/Grazulex/laravel-apiroute/commit/5382d6dbe17b9ccdfd4d76c37385fe6a12653741))
## [2.0.0](https://github.com/Grazulex/laravel-apiroute/releases/tag/v2.0.0) (2026-01-02)

### ⚠ BREAKING CHANGES

- add config-based version registration ([5b6f0e9](https://github.com/Grazulex/laravel-apiroute/commit/5b6f0e93aa39feb42d7c10b35b8869eb31527fa3))
  - API versions should now be declared in config/apiroute.php

### Features

- add config-based version registration ([5b6f0e9](https://github.com/Grazulex/laravel-apiroute/commit/5b6f0e93aa39feb42d7c10b35b8869eb31527fa3))
  - API versions should now be declared in config/apiroute.php

### Bug Fixes

- correct sunset date test expectations ([511b567](https://github.com/Grazulex/laravel-apiroute/commit/511b5674f2f50910fbff5ae02a1d90598347f281))
- read config dynamically and improve tests ([897ccd2](https://github.com/Grazulex/laravel-apiroute/commit/897ccd25f0ad33b9c22e905b95d13c722762cea8))

### Code Refactoring

- remove unused config from ApiRouteManager constructor ([e891f2e](https://github.com/Grazulex/laravel-apiroute/commit/e891f2ec08101d404600f406f2a91118d0977b9b))
- read all config dynamically instead of from constructor ([41f11e4](https://github.com/Grazulex/laravel-apiroute/commit/41f11e4798c31575f0fab2c9d42b1378c15555e2))

### Documentation

- update README for v2.0 config-based version registration ([2ee5781](https://github.com/Grazulex/laravel-apiroute/commit/2ee5781d9d6309a0e9115e7945ed81f2e53bb984))

### Styles

- simplify singleton registration for ApiRouteManager ([21489da](https://github.com/Grazulex/laravel-apiroute/commit/21489daa571480b9996b3265a566cd5555b9ab1d))
## [1.2.0](https://github.com/Grazulex/laravel-apiroute/releases/tag/v1.2.0) (2025-12-28)

### Bug Fixes

- resolve SQL upsert error in DatabaseTracker ([a0ba167](https://github.com/Grazulex/laravel-apiroute/commit/a0ba167f6e1eeaee513c1c379579707d33c2edb6))
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
