# Laravel ApiRoute - Spécification de Développement

> **Package Name:** `grazulex/laravel-apiroute`  
> **Tagline:** "Complete API versioning lifecycle management for Laravel"  
> **Author:** Jean-Marc Strauven (@Grazulex)  
> **License:** MIT  
> **Target:** Laravel 10, 11, 12 | PHP 8.1+

---

## 📋 Table des matières

1. [Vision & Objectifs](#vision--objectifs)
2. [Analyse de marché](#analyse-de-marché)
3. [Fonctionnalités](#fonctionnalités)
4. [Architecture technique](#architecture-technique)
5. [API publique](#api-publique)
6. [Configuration](#configuration)
7. [Middleware](#middleware)
8. [Headers HTTP](#headers-http)
9. [Commandes Artisan](#commandes-artisan)
10. [Structure du package](#structure-du-package)
11. [Base de données](#base-de-données)
12. [Events](#events)
13. [Exceptions](#exceptions)
14. [Tests](#tests)
15. [Roadmap](#roadmap)
16. [Promotion](#promotion)

---

## Vision & Objectifs

### Le problème

Aujourd'hui, versionner une API Laravel requiert du boilerplate manuel répétitif :
- Créer des dossiers `Controllers/Api/V1`, `V2`, etc.
- Dupliquer les fichiers de routes
- Écrire son propre middleware de négociation
- Gérer manuellement les headers de dépréciation
- Aucune visibilité sur le cycle de vie des versions

### La solution

Un package **complet et opinionné** qui gère l'intégralité du cycle de vie d'une API versionnée :
- Configuration simple et déclarative
- Multi-stratégies de versioning (URI, Header, Query)
- Headers de dépréciation automatiques (RFC 8594)
- Commandes Artisan pour scaffolding et monitoring
- Dashboard optionnel pour visualiser l'état des versions

### Objectifs

1. **Simplicité** — Fonctionnel en 5 minutes, configuration minimale
2. **Complétude** — Tout ce qu'il faut pour gérer des versions d'API en production
3. **Standards** — Respect des RFC (8594 pour Deprecation, 7231 pour Sunset)
4. **DX** — Developer Experience exceptionnelle (commandes, messages clairs)
5. **Performance** — Zero overhead mesurable en production

---

## Analyse de marché

### Packages existants

| Package | Downloads | Limitations |
|---------|-----------|-------------|
| `mbpcoder/laravel-api-versioning` | 174k | Basique, juste fallback routes, pas Laravel 11/12 |
| `juliomotol/lapiv` | ~faible | Abandonné, redirige vers spatie/route-attributes |
| `ejunker/laravel-api-evolution` | 149 | Approche Stripe (dates), trop niche |
| `shahghasiadil/laravel-api-versioning` | Récent | Attributes PHP 8, incomplet |
| `tenantcloud/laravel-api-versioning` | ~faible | Usage interne, peu documenté |

### Opportunité

- **Aucun package standard** n'a émergé
- Demande forte (chaque projet API a ce besoin)
- Le marché est fragmenté avec des solutions partielles
- `spatie/laravel-route-attributes` n'est PAS un concurrent (syntaxe only)

### Différenciation

| Feature | Concurrents | laravel-apiroute |
|---------|-------------|------------------|
| Multi-stratégies (URI/Header/Query) | Partiel | ✅ Complet |
| Headers Deprecation/Sunset | ❌ | ✅ RFC 8594/7231 |
| Cycle de vie (active→deprecated→sunset→removed) | ❌ | ✅ |
| Commandes Artisan | ❌ | ✅ |
| Fallback intelligent | Partiel | ✅ |
| Stats d'usage par version | ❌ | ✅ (optionnel) |
| Compatible Laravel 12 | ❌ | ✅ |

---

## Fonctionnalités

### Core Features (MVP)

#### 1. Déclaration des versions
```php
// routes/api.php
use Grazulex\ApiRoute\Facades\ApiRoute;

ApiRoute::version('v1', function () {
    Route::apiResource('users', App\Http\Controllers\Api\V1\UserController::class);
    Route::apiResource('posts', App\Http\Controllers\Api\V1\PostController::class);
})
->deprecated('2025-06-01')
->sunset('2025-12-01');

ApiRoute::version('v2', function () {
    Route::apiResource('users', App\Http\Controllers\Api\V2\UserController::class);
    Route::apiResource('posts', App\Http\Controllers\Api\V2\PostController::class);
})->current();

ApiRoute::version('v3', function () {
    Route::apiResource('users', App\Http\Controllers\Api\V3\UserController::class);
})->beta(); // ou ->preview()
```

#### 2. Multi-stratégies de versioning

**URI Path (défaut)**
```
GET /api/v1/users
GET /api/v2/users
```

**Header**
```
GET /api/users
X-API-Version: 2
# ou
Accept: application/vnd.api.v2+json
```

**Query Parameter**
```
GET /api/users?api_version=2
```

#### 3. Headers automatiques

Sur chaque réponse d'une version dépréciée :
```http
HTTP/1.1 200 OK
Deprecation: Sun, 01 Jun 2025 00:00:00 GMT
Sunset: Mon, 01 Dec 2025 00:00:00 GMT
Link: </api/v2/users>; rel="successor-version"
X-API-Version: v1
X-API-Version-Status: deprecated
```

#### 4. Fallback intelligent

Si une route n'existe pas en V2, fallback vers V1 (configurable) :
```php
// config/apiroute.php
'fallback' => [
    'enabled' => true,
    'strategy' => 'previous', // 'previous', 'latest', 'none'
    'add_header' => true, // X-API-Version-Fallback: v1
]
```

#### 5. Blocage après sunset

```php
// Après la date de sunset, retourne automatiquement :
{
    "error": "api_version_sunset",
    "message": "API version v1 is no longer available. Please upgrade to v2.",
    "sunset_date": "2025-12-01",
    "documentation": "https://api.example.com/docs/migration-v1-to-v2"
}
```

### Features avancées (post-MVP)

#### 6. Statistiques d'usage (optionnel)
```php
// config/apiroute.php
'tracking' => [
    'enabled' => true,
    'driver' => 'database', // 'database', 'redis', 'null'
    'table' => 'api_version_stats',
]
```

#### 7. Rate limiting par version
```php
ApiRoute::version('v1', function () {
    // ...
})->rateLimit(100); // 100 requests/minute pour v1 (encourager migration)

ApiRoute::version('v2', function () {
    // ...
})->rateLimit(1000);
```

#### 8. Notifications
```php
// config/apiroute.php
'notifications' => [
    'channels' => ['slack', 'mail'],
    'events' => [
        'approaching_sunset' => true, // 30, 7, 1 jour avant
        'high_deprecated_usage' => true, // > 50% traffic sur deprecated
    ]
]
```

---

## Architecture technique

### Service Provider

```php
namespace Grazulex\ApiRoute;

use Illuminate\Support\ServiceProvider;

class ApiRouteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/apiroute.php', 'apiroute');
        
        $this->app->singleton(ApiRouteManager::class, function ($app) {
            return new ApiRouteManager($app['config']['apiroute']);
        });
        
        $this->app->singleton(VersionResolver::class, function ($app) {
            return new VersionResolver(
                $app->make(ApiRouteManager::class),
                $app['config']['apiroute']
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/apiroute.php' => config_path('apiroute.php'),
        ], 'apiroute-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'apiroute-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\ApiStatusCommand::class,
                Commands\ApiVersionCommand::class,
                Commands\ApiDeprecateCommand::class,
            ]);
        }

        $this->registerMiddleware();
        $this->registerMacros();
    }
}
```

### Classes principales

```
src/
├── ApiRouteServiceProvider.php
├── Facades/
│   └── ApiRoute.php
├── ApiRouteManager.php          # Gestion centrale des versions
├── VersionResolver.php          # Résolution de la version demandée
├── VersionDefinition.php        # Définition d'une version (dates, status)
├── Middleware/
│   ├── ResolveApiVersion.php    # Middleware principal
│   ├── EnforceApiVersion.php    # Force une version spécifique
│   └── TrackApiUsage.php        # Tracking optionnel
├── Http/
│   ├── Responses/
│   │   ├── DeprecationResponse.php
│   │   └── SunsetResponse.php
│   └── Headers/
│       └── VersionHeaders.php
├── Commands/
│   ├── ApiStatusCommand.php
│   ├── ApiVersionCommand.php
│   └── ApiDeprecateCommand.php
├── Events/
│   ├── VersionDeprecated.php
│   ├── VersionSunset.php
│   └── DeprecatedVersionAccessed.php
├── Exceptions/
│   ├── VersionNotFoundException.php
│   ├── VersionSunsetException.php
│   └── InvalidVersionException.php
├── Contracts/
│   ├── VersionResolverInterface.php
│   └── VersionTrackerInterface.php
└── Support/
    ├── VersionStatus.php         # Enum: active, deprecated, sunset, removed
    └── DetectionStrategy.php     # Enum: uri, header, query, accept
```

---

## API publique

### Facade `ApiRoute`

```php
use Grazulex\ApiRoute\Facades\ApiRoute;

// Définir une version
ApiRoute::version(string $version, Closure $routes): VersionDefinition;

// Obtenir toutes les versions
ApiRoute::versions(): Collection;

// Obtenir une version spécifique
ApiRoute::getVersion(string $version): ?VersionDefinition;

// Obtenir la version courante (marquée current)
ApiRoute::currentVersion(): ?VersionDefinition;

// Obtenir la version depuis une request
ApiRoute::resolveVersion(Request $request): string;

// Vérifier si une version existe
ApiRoute::hasVersion(string $version): bool;

// Vérifier le statut d'une version
ApiRoute::isDeprecated(string $version): bool;
ApiRoute::isSunset(string $version): bool;
ApiRoute::isActive(string $version): bool;
```

### Fluent API pour `VersionDefinition`

```php
ApiRoute::version('v1', fn() => ...)
    ->current()                           // Marquer comme version courante
    ->deprecated(string|Carbon $date)     // Date de dépréciation
    ->sunset(string|Carbon $date)         // Date de fin de vie
    ->beta()                              // Marquer comme beta/preview
    ->successor(string $version)          // Version successeur (pour Link header)
    ->documentation(string $url)          // URL de documentation
    ->rateLimit(int $requests)            // Rate limit spécifique
    ->middleware(array|string $middleware) // Middleware additionnels
    ->name(string $name);                 // Nom pour les routes nommées
```

### Helpers

```php
// Dans un controller
use Grazulex\ApiRoute\Facades\ApiRoute;

class UserController extends Controller
{
    public function index()
    {
        $version = ApiRoute::resolveVersion(request()); // 'v1', 'v2', etc.
        
        // ou via helper
        $version = api_version(); // helper global
        
        // ou via request macro
        $version = request()->apiVersion();
    }
}
```

### Request Macros

```php
// Ajoutés automatiquement
$request->apiVersion();           // string: 'v1'
$request->apiVersionStatus();     // VersionStatus: active, deprecated, etc.
$request->isDeprecatedVersion();  // bool
$request->apiVersionDefinition(); // VersionDefinition
```

---

## Configuration

### Fichier `config/apiroute.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Detection Strategy
    |--------------------------------------------------------------------------
    |
    | How the API version should be detected from incoming requests.
    | Supported: "uri", "header", "query", "accept"
    |
    */
    'strategy' => env('API_VERSION_STRATEGY', 'uri'),

    /*
    |--------------------------------------------------------------------------
    | Strategy Configuration
    |--------------------------------------------------------------------------
    */
    'strategies' => [
        'uri' => [
            'prefix' => 'api',           // /api/v1/users
            'pattern' => 'v{version}',   // v1, v2, etc.
        ],
        'header' => [
            'name' => 'X-API-Version',   // X-API-Version: 1
        ],
        'query' => [
            'parameter' => 'api_version', // ?api_version=1
        ],
        'accept' => [
            'pattern' => 'application/vnd.{vendor}.{version}+json',
            'vendor' => env('API_VENDOR', 'api'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Version
    |--------------------------------------------------------------------------
    |
    | Version to use when none is specified in the request.
    | Set to 'latest' to always use the most recent non-beta version.
    |
    */
    'default_version' => env('API_DEFAULT_VERSION', 'latest'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Behavior
    |--------------------------------------------------------------------------
    |
    | When a route doesn't exist in the requested version, should we
    | fallback to a previous version?
    |
    */
    'fallback' => [
        'enabled' => true,
        'strategy' => 'previous',  // 'previous', 'latest', 'none'
        'add_header' => true,      // Add X-API-Version-Fallback header
    ],

    /*
    |--------------------------------------------------------------------------
    | Sunset Behavior
    |--------------------------------------------------------------------------
    |
    | How to handle requests to sunset (end-of-life) versions.
    |
    */
    'sunset' => [
        'action' => 'reject',      // 'reject', 'warn', 'allow'
        'status_code' => 410,      // HTTP Gone
        'include_migration_url' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Headers
    |--------------------------------------------------------------------------
    |
    | Automatically add version-related headers to responses.
    |
    */
    'headers' => [
        'enabled' => true,
        'include' => [
            'version' => true,           // X-API-Version
            'status' => true,            // X-API-Version-Status
            'deprecation' => true,       // Deprecation (RFC 8594)
            'sunset' => true,            // Sunset (RFC 7231)
            'successor_link' => true,    // Link rel="successor-version"
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Tracking
    |--------------------------------------------------------------------------
    |
    | Track API version usage for analytics and monitoring.
    |
    */
    'tracking' => [
        'enabled' => env('API_VERSION_TRACKING', false),
        'driver' => 'database',      // 'database', 'redis', 'null'
        'table' => 'api_version_stats',
        'aggregate' => 'hourly',     // 'realtime', 'hourly', 'daily'
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Get notified about version lifecycle events.
    |
    */
    'notifications' => [
        'enabled' => false,
        'channels' => ['mail'],
        'recipients' => [],
        'events' => [
            'approaching_deprecation' => [7, 1],  // days before
            'approaching_sunset' => [30, 7, 1],
            'high_deprecated_usage' => 50,        // percentage threshold
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation
    |--------------------------------------------------------------------------
    |
    | URLs for API documentation (used in error responses).
    |
    */
    'documentation' => [
        'base_url' => env('API_DOCS_URL'),
        'migration_guides' => [
            // 'v1' => 'https://docs.example.com/api/migration/v1-to-v2',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware configuration for API version handling.
    |
    */
    'middleware' => [
        'group' => 'api',            // Apply to this middleware group
        'alias' => 'api.version',    // Middleware alias
    ],
];
```

---

## Middleware

### `ResolveApiVersion` (Principal)

```php
<?php

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Illuminate\Http\Request;
use Grazulex\ApiRoute\VersionResolver;
use Grazulex\ApiRoute\Http\Headers\VersionHeaders;
use Grazulex\ApiRoute\Exceptions\VersionSunsetException;
use Grazulex\ApiRoute\Exceptions\VersionNotFoundException;

class ResolveApiVersion
{
    public function __construct(
        private VersionResolver $resolver,
        private VersionHeaders $headers
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // 1. Résoudre la version demandée
        $version = $this->resolver->resolve($request);
        
        // 2. Vérifier que la version existe
        if (!$version) {
            throw new VersionNotFoundException(
                $this->resolver->getRequestedVersion($request)
            );
        }
        
        // 3. Vérifier si sunset
        if ($version->isSunset() && config('apiroute.sunset.action') === 'reject') {
            throw new VersionSunsetException($version);
        }
        
        // 4. Stocker la version dans la request
        $request->attributes->set('api_version', $version->name());
        $request->attributes->set('api_version_definition', $version);
        
        // 5. Dispatcher l'event si version dépréciée
        if ($version->isDeprecated()) {
            event(new DeprecatedVersionAccessed($version, $request));
        }
        
        // 6. Exécuter la request
        $response = $next($request);
        
        // 7. Ajouter les headers de version
        return $this->headers->addToResponse($response, $version);
    }
}
```

### `TrackApiUsage` (Optionnel)

```php
<?php

namespace Grazulex\ApiRoute\Middleware;

use Closure;
use Illuminate\Http\Request;
use Grazulex\ApiRoute\Contracts\VersionTrackerInterface;

class TrackApiUsage
{
    public function __construct(
        private VersionTrackerInterface $tracker
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Track en async pour ne pas impacter la performance
        dispatch(function () use ($request) {
            $this->tracker->track(
                version: $request->attributes->get('api_version'),
                endpoint: $request->path(),
                method: $request->method(),
                status: $response->status()
            );
        })->afterResponse();
        
        return $response;
    }
}
```

---

## Headers HTTP

### Standards implémentés

| Header | Standard | Exemple |
|--------|----------|---------|
| `Deprecation` | RFC 8594 | `Deprecation: Sun, 01 Jun 2025 00:00:00 GMT` |
| `Sunset` | RFC 7231 | `Sunset: Mon, 01 Dec 2025 00:00:00 GMT` |
| `Link` | RFC 8288 | `Link: </api/v2/users>; rel="successor-version"` |
| `X-API-Version` | Custom | `X-API-Version: v1` |
| `X-API-Version-Status` | Custom | `X-API-Version-Status: deprecated` |
| `X-API-Version-Fallback` | Custom | `X-API-Version-Fallback: v1` |

### Classe `VersionHeaders`

```php
<?php

namespace Grazulex\ApiRoute\Http\Headers;

use Illuminate\Http\Response;
use Grazulex\ApiRoute\VersionDefinition;
use Carbon\Carbon;

class VersionHeaders
{
    public function addToResponse(Response $response, VersionDefinition $version): Response
    {
        $config = config('apiroute.headers');
        
        if (!$config['enabled']) {
            return $response;
        }

        // Version actuelle
        if ($config['include']['version']) {
            $response->header('X-API-Version', $version->name());
        }

        // Statut
        if ($config['include']['status']) {
            $response->header('X-API-Version-Status', $version->status()->value);
        }

        // Deprecation header (RFC 8594)
        if ($config['include']['deprecation'] && $version->deprecationDate()) {
            $response->header(
                'Deprecation',
                $version->deprecationDate()->format(Carbon::RFC7231)
            );
        }

        // Sunset header (RFC 7231)
        if ($config['include']['sunset'] && $version->sunsetDate()) {
            $response->header(
                'Sunset',
                $version->sunsetDate()->format(Carbon::RFC7231)
            );
        }

        // Link to successor
        if ($config['include']['successor_link'] && $version->successor()) {
            $successorUrl = $this->buildSuccessorUrl($version);
            $response->header(
                'Link',
                "<{$successorUrl}>; rel=\"successor-version\""
            );
        }

        return $response;
    }
}
```

---

## Commandes Artisan

### `api:status`

Affiche l'état de toutes les versions API.

```bash
php artisan api:status
```

Output :
```
┌─────────┬────────────┬──────────────┬──────────────┬────────────┬────────────────┐
│ Version │ Status     │ Deprecated   │ Sunset       │ Routes     │ Usage (30d)    │
├─────────┼────────────┼──────────────┼──────────────┼────────────┼────────────────┤
│ v3      │ 🧪 beta    │ -            │ -            │ 12 routes  │ 2.1%           │
│ v2      │ ✅ current │ -            │ -            │ 45 routes  │ 78.4%          │
│ v1      │ ⚠️ deprecated │ 2025-06-01 │ 2025-12-01   │ 42 routes  │ 19.5%          │
└─────────┴────────────┴──────────────┴──────────────┴────────────┴────────────────┘

⚠️  Warning: v1 will be sunset in 180 days (2025-12-01)
📊 19.5% of traffic still uses deprecated version v1
```

Options :
```bash
php artisan api:status --version=v1     # Détails d'une version spécifique
php artisan api:status --json           # Output JSON
php artisan api:status --routes         # Inclure la liste des routes
```

### `api:version`

Scaffolde une nouvelle version d'API.

```bash
php artisan api:version v3 --copy-from=v2
```

Actions :
1. Crée `app/Http/Controllers/Api/V3/`
2. Copie les controllers de V2 (si `--copy-from`)
3. Met à jour les namespaces
4. Crée le fichier de routes `routes/api/v3.php` (optionnel)
5. Affiche les instructions suivantes

Options :
```bash
php artisan api:version v3                        # Créer version vide
php artisan api:version v3 --copy-from=v2         # Copier depuis v2
php artisan api:version v3 --controllers=User,Post # Seulement certains controllers
php artisan api:version v3 --force                # Écraser si existe
```

### `api:deprecate`

Marque une version comme dépréciée.

```bash
php artisan api:deprecate v1 --on=2025-06-01 --sunset=2025-12-01
```

Actions :
1. Met à jour la configuration
2. Envoie les notifications configurées
3. Affiche un résumé

Options :
```bash
php artisan api:deprecate v1 --on=2025-06-01      # Date de dépréciation
php artisan api:deprecate v1 --sunset=2025-12-01  # Date de sunset
php artisan api:deprecate v1 --successor=v2       # Version de remplacement
php artisan api:deprecate v1 --notify             # Envoyer notifications
```

### `api:sunset`

Marque une version comme sunset (fin de vie).

```bash
php artisan api:sunset v1 --remove-routes
```

Options :
```bash
php artisan api:sunset v1                   # Marquer comme sunset
php artisan api:sunset v1 --remove-routes   # Supprimer les routes
php artisan api:sunset v1 --archive         # Archiver les controllers
```

### `api:stats`

Affiche les statistiques d'usage (si tracking activé).

```bash
php artisan api:stats --period=30
```

Output :
```
API Version Usage Statistics (Last 30 days)

Total Requests: 1,234,567

By Version:
┌─────────┬────────────┬────────────┬───────────────┐
│ Version │ Requests   │ Percentage │ Trend         │
├─────────┼────────────┼────────────┼───────────────┤
│ v2      │ 967,901    │ 78.4%      │ ↑ +5.2%       │
│ v1      │ 240,741    │ 19.5%      │ ↓ -4.8%       │
│ v3      │ 25,925     │ 2.1%       │ ↑ +0.4%       │
└─────────┴────────────┴────────────┴───────────────┘

Top Endpoints (v1 - deprecated):
1. GET /api/v1/users (89,234 requests)
2. POST /api/v1/auth/login (67,123 requests)
3. GET /api/v1/products (45,678 requests)
```

---

## Structure du package

```
laravel-apiroute/
├── composer.json
├── LICENSE.md
├── README.md
├── CHANGELOG.md
├── CONTRIBUTING.md
├── .github/
│   └── workflows/
│       ├── tests.yml
│       ├── static-analysis.yml
│       └── code-style.yml
├── config/
│   └── apiroute.php
├── database/
│   └── migrations/
│       └── create_api_version_stats_table.php
├── src/
│   ├── ApiRouteServiceProvider.php
│   ├── Facades/
│   │   └── ApiRoute.php
│   ├── ApiRouteManager.php
│   ├── VersionResolver.php
│   ├── VersionDefinition.php
│   ├── Middleware/
│   │   ├── ResolveApiVersion.php
│   │   ├── EnforceApiVersion.php
│   │   └── TrackApiUsage.php
│   ├── Http/
│   │   ├── Responses/
│   │   │   ├── DeprecationResponse.php
│   │   │   └── SunsetResponse.php
│   │   └── Headers/
│   │       └── VersionHeaders.php
│   ├── Commands/
│   │   ├── ApiStatusCommand.php
│   │   ├── ApiVersionCommand.php
│   │   ├── ApiDeprecateCommand.php
│   │   ├── ApiSunsetCommand.php
│   │   └── ApiStatsCommand.php
│   ├── Events/
│   │   ├── VersionDeprecated.php
│   │   ├── VersionSunset.php
│   │   ├── DeprecatedVersionAccessed.php
│   │   └── VersionCreated.php
│   ├── Exceptions/
│   │   ├── VersionNotFoundException.php
│   │   ├── VersionSunsetException.php
│   │   ├── InvalidVersionException.php
│   │   └── ApiRouteException.php
│   ├── Contracts/
│   │   ├── VersionResolverInterface.php
│   │   └── VersionTrackerInterface.php
│   ├── Tracking/
│   │   ├── DatabaseTracker.php
│   │   ├── RedisTracker.php
│   │   └── NullTracker.php
│   └── Support/
│       ├── VersionStatus.php
│       ├── DetectionStrategy.php
│       └── helpers.php
├── stubs/
│   ├── controller.stub
│   └── routes.stub
└── tests/
    ├── TestCase.php
    ├── Feature/
    │   ├── VersionResolutionTest.php
    │   ├── HeadersTest.php
    │   ├── FallbackTest.php
    │   ├── SunsetTest.php
    │   └── CommandsTest.php
    └── Unit/
        ├── VersionDefinitionTest.php
        ├── VersionResolverTest.php
        └── VersionStatusTest.php
```

---

## Base de données

### Migration (optionnelle, pour tracking)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_version_stats', function (Blueprint $table) {
            $table->id();
            $table->string('version', 10)->index();
            $table->string('endpoint')->index();
            $table->string('method', 10);
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->date('date')->index();
            $table->unsignedTinyInteger('hour')->nullable(); // Pour agrégation horaire
            $table->timestamps();
            
            $table->unique(['version', 'endpoint', 'method', 'date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_version_stats');
    }
};
```

---

## Events

### `DeprecatedVersionAccessed`

```php
<?php

namespace Grazulex\ApiRoute\Events;

use Illuminate\Http\Request;
use Grazulex\ApiRoute\VersionDefinition;

class DeprecatedVersionAccessed
{
    public function __construct(
        public VersionDefinition $version,
        public Request $request
    ) {}
}
```

### `VersionSunset`

```php
<?php

namespace Grazulex\ApiRoute\Events;

use Grazulex\ApiRoute\VersionDefinition;

class VersionSunset
{
    public function __construct(
        public VersionDefinition $version
    ) {}
}
```

### Usage

```php
// Dans EventServiceProvider ou via listener
Event::listen(DeprecatedVersionAccessed::class, function ($event) {
    Log::warning("Deprecated API version accessed", [
        'version' => $event->version->name(),
        'endpoint' => $event->request->path(),
        'ip' => $event->request->ip(),
    ]);
});
```

---

## Exceptions

### `VersionNotFoundException`

```php
<?php

namespace Grazulex\ApiRoute\Exceptions;

use Exception;
use Illuminate\Http\Request;

class VersionNotFoundException extends Exception
{
    public function __construct(
        public string $requestedVersion
    ) {
        parent::__construct("API version '{$requestedVersion}' not found.");
    }

    public function render(Request $request)
    {
        return response()->json([
            'error' => 'version_not_found',
            'message' => $this->getMessage(),
            'requested_version' => $this->requestedVersion,
            'available_versions' => ApiRoute::versions()->pluck('name'),
        ], 404);
    }
}
```

### `VersionSunsetException`

```php
<?php

namespace Grazulex\ApiRoute\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Grazulex\ApiRoute\VersionDefinition;

class VersionSunsetException extends Exception
{
    public function __construct(
        public VersionDefinition $version
    ) {
        parent::__construct("API version '{$version->name()}' has been sunset.");
    }

    public function render(Request $request)
    {
        $config = config('apiroute');
        
        return response()->json([
            'error' => 'api_version_sunset',
            'message' => "API version {$this->version->name()} is no longer available.",
            'sunset_date' => $this->version->sunsetDate()?->toIso8601String(),
            'successor' => $this->version->successor(),
            'migration_guide' => $config['documentation']['migration_guides'][$this->version->name()] ?? null,
        ], $config['sunset']['status_code']);
    }
}
```

---

## Tests

### Structure des tests

```php
// tests/Feature/VersionResolutionTest.php

use Grazulex\ApiRoute\Facades\ApiRoute;

test('resolves version from URI path', function () {
    ApiRoute::version('v1', fn() => Route::get('test', fn() => 'v1'));
    ApiRoute::version('v2', fn() => Route::get('test', fn() => 'v2'));

    $response = $this->get('/api/v1/test');
    
    $response->assertOk();
    $response->assertContent('v1');
    $response->assertHeader('X-API-Version', 'v1');
});

test('resolves version from header', function () {
    config(['apiroute.strategy' => 'header']);
    
    ApiRoute::version('v1', fn() => Route::get('test', fn() => 'v1'));
    ApiRoute::version('v2', fn() => Route::get('test', fn() => 'v2'));

    $response = $this->withHeader('X-API-Version', '2')->get('/api/test');
    
    $response->assertOk();
    $response->assertContent('v2');
});

test('adds deprecation headers for deprecated version', function () {
    ApiRoute::version('v1', fn() => Route::get('test', fn() => 'v1'))
        ->deprecated('2025-06-01')
        ->sunset('2025-12-01');

    $response = $this->get('/api/v1/test');
    
    $response->assertOk();
    $response->assertHeader('Deprecation');
    $response->assertHeader('Sunset');
    $response->assertHeader('X-API-Version-Status', 'deprecated');
});

test('rejects sunset version with 410 Gone', function () {
    ApiRoute::version('v1', fn() => Route::get('test', fn() => 'v1'))
        ->sunset(now()->subDay());

    $response = $this->get('/api/v1/test');
    
    $response->assertStatus(410);
    $response->assertJson(['error' => 'api_version_sunset']);
});

test('falls back to previous version when route not found', function () {
    ApiRoute::version('v1', fn() => Route::get('legacy', fn() => 'legacy'));
    ApiRoute::version('v2', fn() => Route::get('new', fn() => 'new'));

    // Route 'legacy' n'existe pas en v2, fallback vers v1
    $response = $this->get('/api/v2/legacy');
    
    $response->assertOk();
    $response->assertContent('legacy');
    $response->assertHeader('X-API-Version-Fallback', 'v1');
});
```

### Couverture minimale requise

- [ ] Résolution de version (URI, Header, Query, Accept)
- [ ] Headers automatiques (Deprecation, Sunset, Link)
- [ ] Fallback behavior
- [ ] Sunset rejection
- [ ] Events dispatch
- [ ] Commandes Artisan
- [ ] Exceptions rendering
- [ ] Configuration variations

---

## Roadmap

### Phase 1 : MVP (Semaines 1-2)

- [ ] Structure du package (ServiceProvider, Facade)
- [ ] `ApiRoute::version()` avec Closure
- [ ] `VersionDefinition` avec fluent API
- [ ] `VersionResolver` (URI strategy seulement)
- [ ] Middleware `ResolveApiVersion`
- [ ] Headers automatiques (X-API-Version, Deprecation, Sunset)
- [ ] Exceptions (VersionNotFound, VersionSunset)
- [ ] Configuration de base
- [ ] Tests unitaires et feature
- [ ] README complet

### Phase 2 : Complet (Semaines 3-4)

- [ ] Strategies additionnelles (Header, Query, Accept)
- [ ] Fallback intelligent
- [ ] Commande `api:status`
- [ ] Commande `api:version` (scaffolding)
- [ ] Events (DeprecatedVersionAccessed, etc.)
- [ ] Request macros (`$request->apiVersion()`)
- [ ] Helper `api_version()`

### Phase 3 : Avancé (Post-launch)

- [ ] Tracking d'usage (database/redis)
- [ ] Commande `api:stats`
- [ ] Commande `api:deprecate`
- [ ] Notifications (Slack, Mail)
- [ ] Intégration Filament (dashboard optionnel)
- [ ] Rate limiting par version

---

## Promotion

### Canaux de publication

1. **Laravel News** — Article soumis dès release
2. **X/Twitter** — Thread de présentation
3. **LinkedIn** — Post avec démo
4. **Reddit r/laravel** — Show HN style
5. **Dev.to** — Article technique
6. **Laracasts Discuss** — Annonce
7. **Packagist** — Tags et description optimisés

### Points clés marketing

- "Le premier package complet pour gérer le cycle de vie de vos APIs Laravel"
- "Headers RFC 8594 et RFC 7231 automatiques"
- "De la définition à la dépréciation en une seule commande"
- "Zero configuration pour démarrer, entièrement personnalisable"
- "Compatible Laravel 10, 11, 12"

### Demo GIF

Créer un GIF montrant :
1. Définition de deux versions dans `routes/api.php`
2. `php artisan api:status` montrant les versions
3. Requête curl montrant les headers

### Site web (optionnel)

`apiroute.dev` ou `laravel-apiroute.dev` avec :
- Demo interactive
- Documentation
- Exemples de code
- Changelog

---

## Notes de développement

### Conventions

- **Code style** : Laravel Pint avec preset `laravel`
- **Static analysis** : PHPStan level 8
- **Tests** : Pest PHP
- **Minimum PHP** : 8.1
- **Minimum Laravel** : 10.0

### Dépendances

```json
{
    "require": {
        "php": "^8.1",
        "illuminate/support": "^10.0|^11.0|^12.0",
        "illuminate/routing": "^10.0|^11.0|^12.0",
        "illuminate/http": "^10.0|^11.0|^12.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0|^10.0",
        "pestphp/pest": "^2.0|^3.0",
        "pestphp/pest-plugin-laravel": "^2.0|^3.0",
        "laravel/pint": "^1.0",
        "phpstan/phpstan": "^1.0"
    }
}
```

### Checklist avant release

- [ ] README complet avec exemples
- [ ] CHANGELOG initialisé
- [ ] LICENSE MIT
- [ ] Tests passent (100% green)
- [ ] PHPStan level 8 clean
- [ ] Pint clean
- [ ] GitHub Actions configuré
- [ ] Packagist configuré
- [ ] Tags Git créés
- [ ] Article Laravel News prêt

---

## Références

### Standards

- [RFC 8594 - The Sunset HTTP Header Field](https://datatracker.ietf.org/doc/html/rfc8594)
- [RFC 8288 - Web Linking](https://datatracker.ietf.org/doc/html/rfc8288)
- [API Deprecation Header (draft)](https://datatracker.ietf.org/doc/html/draft-ietf-httpapi-deprecation-header)

### Inspiration

- [Stripe API Versioning](https://stripe.com/blog/api-versioning)
- [GitHub API Versioning](https://docs.github.com/en/rest/overview/api-versions)
- [Twilio API Versioning](https://www.twilio.com/docs/usage/api/api-versioning)

### Articles

- [Laravel News - API Versioning in Laravel 11](https://laravel-news.com/api-versioning-in-laravel-11)
- [Treblle - API Versioning Best Practices](https://blog.treblle.com/api-versioning-in-laravel-the-complete-guide-to-doing-it-right/)

---

*Document créé le 23 décembre 2025*  
*Auteur : Jean-Marc Strauven (@Grazulex)*
