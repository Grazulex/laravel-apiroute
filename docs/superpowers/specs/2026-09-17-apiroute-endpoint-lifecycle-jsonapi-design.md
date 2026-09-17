# ApiRoute — Endpoint lifecycle attributes & JSON:API — Design

**Date** : 2026-09-17
**Package** : `grazulex/laravel-apiroute` (v2.1.1 → v2.2.0, additif, rétro-compatible)
**Compatibilité** : Laravel 12 et 13, PHP ≥ 8.3

## 1. Objectif

1. Permettre de déprécier un **endpoint** (et non plus seulement une version entière) avec
   un attribut PHP `#[Deprecated]` sur un contrôleur ou une méthode — ou une macro de route
   pour les closures — et en tirer les en-têtes HTTP standard (`Deprecation`, `Sunset`,
   `Link`) ainsi que le rejet 410 après la date de sunset, selon la politique déjà
   configurée pour les versions.
2. Parler **JSON:API** quand le client le demande : documents d'erreur JSON:API pour les
   erreurs de version/endpoint (L12 et L13), et un trait pour enrichir les
   `JsonApiResource` de Laravel 13 avec les métadonnées de version.

Hors périmètre : routage par attributs (découverte de contrôleurs), changement du format
de l'en-tête `Deprecation` (voir §7), JSON:API pour `api:stats`, configuration forçant le
format JSON:API.

## 2. Décisions

| Sujet | Décision |
|---|---|
| Source de vérité des routes | inchangée : fichiers de routes par version |
| Attribut | `Grazulex\ApiRoute\Attributes\Deprecated(since, sunset, successor, docs, reason)` sur classe ou méthode ; la méthode prime champ par champ |
| Closures | macro `->deprecated(since:, sunset:, successor:, docs:, reason:)` sur la route, même structure |
| Sunset dépassé | même politique que les versions : `config('apiroute.sunset.action')` (`reject` → 410, `warn` → en-têtes, `allow` → rien) |
| `successor` | route nommée (`Route::has()`) → `route()` ; chemin commençant par `/` → `url()` ; sinon tel quel |
| Mécanisme | middleware `EnforceEndpointSunset` (410 avant le contrôleur) + extension du listener `AddVersionHeadersToResponse` (en-têtes après ceux de la version) ; réflexion au runtime mémorisée par action |
| JSON:API | négociation de contenu (`Accept`/`Content-Type: application/vnd.api+json`) ; documents d'erreur produits sans dépendre d'une classe L13 ; trait opt-in pour `JsonApiResource` (L13) |
| Config | une clé nouvelle : `headers.include.endpoint_status` |

## 3. Attribut, valeur, macro, résolution

### 3.1 `Grazulex\ApiRoute\Attributes\Deprecated`

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Deprecated
{
    public function __construct(
        public ?string $since = null,      // date, Carbon::parse()
        public ?string $sunset = null,     // date, Carbon::parse()
        public ?string $successor = null,  // route nommée, chemin ou URL
        public ?string $docs = null,       // URL → Link rel="deprecation"
        public ?string $reason = null,     // détail du document d'erreur 410
    ) {}
}
```

Nom : `Deprecated` dans notre namespace. PHP 8.4 fournit `#[\Deprecated]` (global) qui
émet un avertissement à l'appel ; les deux coexistent et peuvent être posés ensemble.
Le README montre `use Grazulex\ApiRoute\Attributes\Deprecated;` et mentionne la
cohabitation.

### 3.2 `Grazulex\ApiRoute\Support\EndpointLifecycle`

```php
final readonly class EndpointLifecycle
{
    public function __construct(
        public ?Carbon $deprecatedAt,
        public ?Carbon $sunsetAt,
        public ?string $successor,   // brut, non résolu
        public ?string $docs,
        public ?string $reason,
    ) {}

    public static function fromArray(array $data): self;   // clés since|sunset|successor|docs|reason
    public function merge(self $override): self;            // champs non null de $override gagnent
    public function isDeprecated(): bool;                    // deprecatedAt ou sunsetAt non null
    public function isSunset(?Carbon $now = null): bool;     // sunsetAt <= now
}
```

### 3.3 Macro de route

`Route::macro('deprecated', fn (?string $since = null, ?string $sunset = null, ?string $successor = null, ?string $docs = null, ?string $reason = null) => $this->setAction($this->getAction() + ['apiroute.deprecated' => compact(...)]))`
enregistrée dans `ApiRouteServiceProvider::boot()`. Utilisable après `Route::get(...)`,
sur closures comme sur contrôleurs (la macro prime alors sur les attributs).

### 3.4 `Grazulex\ApiRoute\Support\EndpointLifecycleResolver`

```php
final class EndpointLifecycleResolver
{
    /** @var array<string, EndpointLifecycle|null> mémo par nom d'action */
    private static array $cache = [];

    public function forRoute(Route $route): ?EndpointLifecycle;
    public function forRequest(Request $request): ?EndpointLifecycle;  // route matchée ou null
    public function resolveSuccessorUrl(EndpointLifecycle $lifecycle): ?string;
    public static function flush(): void;                               // tests
}
```

`forRoute()` :
1. `$route->getAction('apiroute.deprecated')` (macro) → `EndpointLifecycle::fromArray()`.
2. Sinon, si `$route->getActionName()` est `Classe@methode` : attributs `Deprecated` de la
   classe puis de la méthode (`ReflectionClass`/`ReflectionMethod::getAttributes(Deprecated::class)`),
   fusionnés (`classe->merge(methode)`). Un seul attribut par cible (le premier est pris).
3. Sinon `null`. Résultat mémorisé par `getActionName()` (les closures ont un nom d'action
   `Closure` non discriminant : la macro est la seule voie pour elles, et son résultat est
   lu à chaque appel sans mémo).

`resolveSuccessorUrl()` :
- `Route::has($successor)` → `route($successor)` ;
- commence par `/` → `url($successor)` ;
- sinon la valeur telle quelle (URL absolue ou identifiant libre).
- Si `$successor` ressemble à un nom de route (contient `.`, pas de `/` ni `://`) et que
  `Route::has()` est faux : `InvalidArgumentException` en `local`/`testing`,
  `Log::warning('[apiroute] unknown successor route', [...])` + `null` ailleurs.

## 4. Pipeline HTTP

### 4.1 En-têtes — `Grazulex\ApiRoute\Http\Headers\EndpointHeaders`

Appelé par `AddVersionHeadersToResponse::handle()` **après** `VersionHeaders::addToResponse()`
quand `EndpointLifecycleResolver::forRequest()` renvoie une valeur. Respecte
`config('apiroute.headers.enabled')` et `headers.include.*` (mêmes clés que les versions,
plus `endpoint_status`). Les valeurs endpoint remplacent celles de la version (plus
spécifiques) ; `X-API-Version*` restent ceux de la version.

| Condition | En-tête |
|---|---|
| `deprecatedAt` et `include.deprecation` | `Deprecation: <date Carbon::RFC7231>` (même format que les versions, voir §7) |
| `sunsetAt` et `include.sunset` | `Sunset: <date Carbon::RFC7231>` |
| `successor` résolu et `include.successor_link` | `Link: <url>; rel="successor-version"` |
| `docs` et `include.successor_link` | `Link: <docs>; rel="deprecation"` (combiné au précédent par virgule) |
| `include.endpoint_status` | `X-API-Endpoint-Status: deprecated` ou `sunset` |

### 4.2 410 — `Grazulex\ApiRoute\Middleware\EnforceEndpointSunset`

Alias `api.endpoint-sunset`, ajouté par `ApiRouteManager::getMiddleware()` en **dernière**
position du groupe de chaque version (après `api.version`, pour que le contexte de version
soit posé). Logique :

```php
$lifecycle = $this->resolver->forRequest($request);
if ($lifecycle?->isSunset() && config('apiroute.sunset.action', 'reject') === 'reject') {
    throw new EndpointSunsetException($lifecycle, $this->resolver->resolveSuccessorUrl($lifecycle));
}
return $next($request);
```

`EndpointSunsetException extends ApiRouteException`, `render(Request)` →
`config('apiroute.sunset.status_code', 410)` avec, sans négociation JSON:API :

```json
{"error":"Endpoint sunset","message":"<reason ou message par défaut>","sunset_at":"<ISO 8601>","successor":"<url|null>","docs":"<url|null>"}
```

et avec négociation, le document JSON:API de §5.2 (`code: endpoint_sunset`).

### 4.3 `api:status`

Nouvelle section « Deprecated endpoints » : parcourt `Route::getRoutes()`, garde les
routes dont `forRoute()` est non null, affiche méthode(s), URI, version (préfixe/groupe),
`since`, `sunset`, `successor`, et un marqueur `SUNSET` si la date est passée. Inclus dans
la sortie `--json` sous `deprecated_endpoints`.

## 5. JSON:API

### 5.1 Négociation — `Grazulex\ApiRoute\Http\JsonApi`

`JsonApi::wanted(Request $request): bool` : vrai si l'en-tête `Accept` contient
`application/vnd.api+json` ou si `Content-Type` commence par `application/vnd.api+json`.
`JsonApi::MEDIA_TYPE = 'application/vnd.api+json'`.

### 5.2 Documents d'erreur — `Grazulex\ApiRoute\Http\Responses\JsonApiErrorDocument`

```php
public static function make(int $status, string $code, string $title, ?string $detail = null, array $links = [], array $meta = []): JsonResponse
```

Corps (les clés vides sont omises) :

```json
{"errors":[{"status":"410","code":"endpoint_sunset","title":"Endpoint sunset","detail":"…","links":{"about":"<docs>","successor":"<url>"},"meta":{"sunset_at":"…"}}]}
```

En-tête `Content-Type: application/vnd.api+json`. Branché dans `render()` de :

| Exception | status | code | links | meta |
|---|---|---|---|---|
| `VersionNotFoundException` | 404 | `version_not_found` | — | `requested_version`, `available_versions` (mêmes données que le JSON actuel) |
| `InvalidVersionException` | 400 | `invalid_version` | — | idem JSON actuel |
| `VersionSunsetException` | 410 | `version_sunset` | `successor` (si `include_migration_url`) | `sunset_at`, `version` |
| `EndpointSunsetException` | 410 | `endpoint_sunset` | `about` (docs), `successor` | `sunset_at` |

Sans négociation, les corps JSON actuels sont **byte-identiques** à aujourd'hui.

### 5.3 Trait — `Grazulex\ApiRoute\Http\Resources\InteractsWithApiVersion`

Pour les `Illuminate\Http\Resources\JsonApi\JsonApiResource` de Laravel 13 :

```php
trait InteractsWithApiVersion
{
    public function with($request): array
    {
        return array_merge_recursive(parent::with($request), $this->apiVersionDocumentMembers($request));
    }

    /** @return array{meta?: array{api: array<string, string>}, links?: array{successor: string}} */
    protected function apiVersionDocumentMembers(Request $request): array;
}
```

`apiVersionDocumentMembers()` lit `ApiVersionContext` (version courante : `version`,
`status`, `deprecation`, `sunset`, `successor` — dates ISO 8601) puis
`EndpointLifecycleResolver::forRequest()` qui **écrase** `deprecation`/`sunset`/`successor`
s'il est présent. Les clés sans valeur sont omises ; s'il n'y a ni version ni endpoint,
le trait renvoie `[]`. Le trait ne mentionne aucune classe L13 par type : il est
autoloadable sur L12 (inutile sans `JsonApiResource`), testé sur L13 uniquement.

## 6. Configuration, documentation, tests

**Config** : `config/apiroute.php` → `headers.include.endpoint_status => true` (commentaire
`// X-API-Endpoint-Status`). Rien d'autre.

**Provider** : macro `deprecated`, alias `api.endpoint-sunset`, bindings singletons
`EndpointLifecycleResolver`, `EndpointHeaders`.

**README** : sections « Deprecating a single endpoint » (attribut + macro + tableau des
en-têtes + politique sunset), « JSON:API » (négociation, exemple de document d'erreur,
trait sur L13). Liste des features : deux puces.

**CHANGELOG** : `[v2.2.0]` — Added : attribut `#[Deprecated]`, macro `deprecated()`,
middleware `api.endpoint-sunset`, en-tête `X-API-Endpoint-Status`, section `api:status`,
documents d'erreur JSON:API par négociation, trait `InteractsWithApiVersion` (L13).

**Tests** (suite actuelle : 93 ; matrice PHP 8.3/8.4 × L12/L13 × lowest/stable inchangée) :

| Niveau | Cas |
|---|---|
| Unit | `EndpointLifecycle` : `fromArray`, `merge` (override champ par champ), `isDeprecated`, `isSunset` (avant/après/égal) ; `EndpointLifecycleResolver` : attribut méthode > classe, macro > attribut, `null` sans rien, mémo (deux appels → une réflexion, via compteur sur un contrôleur de test), successeur route nommée / chemin / URL absolue, route nommée inconnue → exception en testing ; `JsonApiErrorDocument` : structure exacte, omission des clés vides, Content-Type ; `JsonApi::wanted` : Accept, Content-Type, aucun, `application/json` |
| Feature | endpoint déprécié dans une version active → `Deprecation`, `Sunset`, `Link` (2 valeurs), `X-API-Endpoint-Status: deprecated` ; endpoint sunset avec `reject` → 410 JSON (corps exact) puis 410 JSON:API avec `Accept` ; `warn` → 200 + en-têtes, statut `sunset` ; `allow` → 200 sans en-têtes endpoint ; version dépréciée + endpoint plus précis → valeurs endpoint, `X-API-Version-Status` de la version ; closure + macro → mêmes en-têtes ; macro sur contrôleur annoté → la macro gagne ; `api:status` affiche l'endpoint et `--json` le contient ; 404 version inconnue en JSON:API sous négociation, et corps JSON inchangé sans (comparaison à une chaîne attendue figée) |
| Trait (L13) | `JsonApiResource` de test + trait → `meta.api.version`, `meta.api.status`, `links.successor` ; endpoint déprécié → `meta.api.deprecation` de l'endpoint ; `skip` si `JsonApiResource` absente |
| Régression | les 93 tests existants passent sans modification |

## 7. Dette notée (hors périmètre)

- Format de l'en-tête `Deprecation` : le package émet une date `RFC7231` (brouillon de 2019) ;
  la RFC 9745 finale impose `@<timestamp-unix>`. Migration à faire pour versions et
  endpoints ensemble, derrière une option (`headers.deprecation_format = 'rfc7231'|'rfc9745'`),
  dans une version ultérieure.
- `AddVersionHeadersToResponse` a déjà quatre stratégies de résolution de version ; l'ajout
  des en-têtes endpoint s'y greffe sans le restructurer.
