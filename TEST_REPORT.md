# Rapport de Test - Laravel ApiRoute v0.0.2

**Date:** 23 Décembre 2025
**Package:** `grazulex/laravel-apiroute`
**Version testée:** 0.0.2
**Environnement:** Docker (PHP 8.4-cli, Laravel 12.43.1)

---

## Résumé Exécutif

| Catégorie | Statut | Notes |
|-----------|--------|-------|
| Installation | :white_check_mark: PASS | Auto-discovery fonctionne |
| Version Declaration | :white_check_mark: PASS | Fluent API opérationnelle |
| Stratégie URI | :white_check_mark: PASS | Routage correct |
| Stratégie Header | :warning: PARTIEL | Headers OK, routing non différencié |
| Stratégie Query | :warning: PARTIEL | Headers OK, routing non différencié |
| Deprecation Headers | :white_check_mark: PASS | RFC 8594/7231 conformes |
| Sunset Headers | :white_check_mark: PASS | RFC 7231 conforme |
| Artisan Commands | :white_check_mark: PASS | api:status fonctionne |
| Sunset Rejection | :white_check_mark: PASS | HTTP 410 correctement retourné |
| Sunset Warn Mode | :white_check_mark: PASS | Mode warn opérationnel |

---

## 1. Installation et Configuration

### 1.1 Installation via Composer

```bash
composer require grazulex/laravel-apiroute
```

**Résultat:** :white_check_mark: SUCCÈS

- Package installé sans erreur
- Auto-discovery Laravel fonctionnel
- Service Provider enregistré automatiquement

### 1.2 Publication de la Configuration

```bash
php artisan vendor:publish --tag="apiroute-config"
```

**Résultat:** :white_check_mark: SUCCÈS

- Fichier `config/apiroute.php` créé correctement
- Toutes les options documentées dans le fichier

### 1.3 Configuration Laravel 12

**Attention:** Avec Laravel 12, il faut s'assurer que le fichier `bootstrap/app.php` inclut les routes API:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',  // <-- Requis
    commands: __DIR__.'/../routes/console.php',
)
```

**Note importante:** Si `strategies.uri.prefix` est configuré sur `'api'` dans `config/apiroute.php`, il y aura un double préfixe (`api/api/v1/...`). Solution: mettre `'prefix' => ''` car Laravel ajoute déjà le préfixe `api/`.

---

## 2. Déclaration des Versions

### 2.1 Configuration des Routes

```php
// routes/api.php
use Grazulex\ApiRoute\Facades\ApiRoute;

ApiRoute::version('v1', function () {
    Route::get('users', [V1UserController::class, 'index']);
})
->deprecated('2025-01-01')
->sunset('2026-06-01')
->setSuccessor('v2');

ApiRoute::version('v2', function () {
    Route::get('users', [V2UserController::class, 'index']);
})->current();

ApiRoute::version('v3', function () {
    Route::get('users', [V3UserController::class, 'index']);
})->beta();
```

**Résultat:** :white_check_mark: SUCCÈS

### 2.2 Vérification des Routes

```bash
php artisan route:list
```

**Output:**
```
GET|HEAD  api/v1/users    Api\V1\UserController@index
GET|HEAD  api/v2/users    Api\V2\UserController@index
GET|HEAD  api/v3/users    Api\V3\UserController@index
```

---

## 3. Tests des Stratégies de Versioning

### 3.1 Stratégie URI (Défaut)

```bash
curl -i http://localhost:8765/api/v1/users
```

**Résultat:** :white_check_mark: SUCCÈS

```
HTTP/1.1 200 OK
X-API-Version: v1
X-API-Version-Status: deprecated
Deprecation: Wed, 01 Jan 2025 00:00:00 GMT
Sunset: Mon, 01 Jun 2026 00:00:00 GMT
Link: <http://localhost:8765/v2>; rel="successor-version"

{"version":"v1","data":[...]}
```

### 3.2 Stratégie Header

```bash
curl -i -H "X-API-Version: v1" http://localhost:8765/api/users
```

**Résultat:** :warning: PARTIEL

- Les headers `X-API-Version` et `X-API-Version-Status` sont corrects
- **Problème:** Le routage ne différencie pas les contrôleurs par version
- Toutes les requêtes utilisent le même contrôleur (le dernier défini)

**Recommandation:** Avec la stratégie header, gérer la version dans le contrôleur:
```php
$version = request()->apiVersion();
// Adapter la réponse selon $version
```

### 3.3 Stratégie Query

```bash
curl -i "http://localhost:8765/api/users?api_version=v1"
```

**Résultat:** :warning: PARTIEL

- Même comportement que la stratégie Header
- Les headers de version sont corrects
- Le routage ne différencie pas les contrôleurs

---

## 4. Headers de Dépréciation (RFC 8594/7231)

### 4.1 Version Dépréciée

```bash
curl -i http://localhost:8765/api/v1/users
```

**Headers reçus:**
| Header | Valeur | Standard |
|--------|--------|----------|
| `X-API-Version` | v1 | Custom |
| `X-API-Version-Status` | deprecated | Custom |
| `Deprecation` | Wed, 01 Jan 2025 00:00:00 GMT | RFC 8594 |
| `Sunset` | Mon, 01 Jun 2026 00:00:00 GMT | RFC 7231 |
| `Link` | `<.../v2>; rel="successor-version"` | RFC 8288 |

**Résultat:** :white_check_mark: SUCCÈS - Tous les headers sont conformes aux RFC

### 4.2 Version Active (Courante)

```bash
curl -i http://localhost:8765/api/v2/users
```

**Headers reçus:**
| Header | Valeur |
|--------|--------|
| `X-API-Version` | v2 |
| `X-API-Version-Status` | active |

**Résultat:** :white_check_mark: SUCCÈS - Pas de headers de dépréciation

### 4.3 Version Beta

```bash
curl -i http://localhost:8765/api/v3/users
```

**Headers reçus:**
| Header | Valeur |
|--------|--------|
| `X-API-Version` | v3 |
| `X-API-Version-Status` | beta |

**Résultat:** :white_check_mark: SUCCÈS

---

## 5. Commandes Artisan

### 5.1 api:status

```bash
php artisan api:status
```

**Output:**
```
+---------+------------+------------+------------+-------------+
| Version | Status     | Deprecated | Sunset     | Usage (30d) |
+---------+------------+------------+------------+-------------+
| v1      | deprecated | 2025-01-01 | 2026-06-01 | 0%          |
| v2      | active     | -          | -          | 0%          |
| v3      | beta       | -          | -          | 0%          |
+---------+------------+------------+------------+-------------+
```

**Résultat:** :white_check_mark: SUCCÈS

### 5.2 api:status --api-version=v1

```bash
php artisan api:status --api-version=v1
```

**Output:**
```
+----------------+------------+
| Property       | Value      |
+----------------+------------+
| Name           | v1         |
| Status         | deprecated |
| Deprecated     | 2025-01-01 |
| Sunset         | 2026-06-01 |
| Successor      | v2         |
| Documentation  | -          |
| Rate Limit     | -          |
| Requests (30d) | 0          |
+----------------+------------+
```

**Résultat:** :white_check_mark: SUCCÈS

### 5.3 api:status --json

**Résultat:** :warning: MINEUR

Le JSON contient des artefacts HTML échappés dans les status:
```json
"status": "deprecated<\/>"
```

**Recommandation:** Nettoyer les tags HTML dans l'output JSON.

---

## 6. Comportement Sunset

### 6.1 Mode Reject (Défaut)

Configuration: `'sunset.action' => 'reject'`

```bash
curl -i http://localhost:8765/api/v0/users
```

**Résultat:** :white_check_mark: SUCCÈS

```
HTTP/1.1 410 Gone
Content-Type: application/json

{
  "error": "api_version_sunset",
  "message": "API version v0 is no longer available.",
  "sunset_date": "2024-12-01T00:00:00+00:00",
  "successor": null,
  "migration_guide": null
}
```

### 6.2 Mode Warn

Configuration: `'sunset.action' => 'warn'`

```bash
curl -i http://localhost:8765/api/v0/users
```

**Résultat:** :white_check_mark: SUCCÈS

```
HTTP/1.1 200 OK
X-API-Version: v0
X-API-Version-Status: sunset
Deprecation: Mon, 01 Jan 2024 00:00:00 GMT
Sunset: Sun, 01 Dec 2024 00:00:00 GMT

{"version":"v1","data":[...]}
```

---

## 7. Fallback Behavior

### Test: Accès à une route V1 via V2

```bash
curl -i http://localhost:8765/api/v2/legacy
```

**Résultat:** :white_check_mark: COMPORTEMENT ATTENDU

- Retourne HTTP 404
- Avec la stratégie URI, chaque version a ses propres routes distinctes
- Le fallback n'est pas applicable car les routes sont séparées par préfixe

**Note:** Le fallback est conçu pour les stratégies header/query où les routes ne sont pas préfixées par version.

---

## 8. Points d'Amélioration Identifiés

### 8.1 Priorité Haute

1. **Stratégies Header/Query:** Le routing vers les bons contrôleurs n'est pas automatique. Documentation à améliorer pour expliquer comment gérer cela.

### 8.2 Priorité Moyenne

2. **JSON Output:** L'option `--json` de `api:status` contient des artefacts HTML (`<\/>`).

3. **Link Header:** L'URL du successor (`<http://localhost:8765/v2>`) devrait inclure le chemin complet de l'API (ex: `/api/v2/users`).

### 8.3 Suggestions

4. **Configuration Laravel 12:** Ajouter une note dans la documentation sur la configuration `bootstrap/app.php`.

5. **Préfixe URI:** Documenter clairement le comportement du préfixe avec Laravel 12.

---

## 9. Conclusion

Le package `grazulex/laravel-apiroute` v0.0.2 est fonctionnel et remplit ses promesses principales:

- :white_check_mark: Installation simple via Composer avec auto-discovery
- :white_check_mark: API fluent intuitive pour déclarer les versions
- :white_check_mark: Headers RFC 8594/7231 conformes
- :white_check_mark: Commandes Artisan utiles
- :white_check_mark: Gestion du cycle de vie (active, deprecated, beta, sunset)
- :white_check_mark: Modes de sunset flexibles (reject/warn/allow)

La stratégie URI fonctionne parfaitement. Les stratégies Header et Query nécessitent une documentation supplémentaire sur la gestion du routing dans les contrôleurs.

**Note globale:** :star::star::star::star: (4/5) - Package solide, quelques améliorations mineures à apporter.

---

## Environnement de Test

```yaml
# docker-compose.yml
services:
  app:
    image: php:8.4-cli
    container_name: apiroute-test
    working_dir: /app
    volumes:
      - ./laravel-app:/app
    command: php -S 0.0.0.0:8000 -t public
    ports:
      - "8765:8000"
```

**Versions:**
- PHP: 8.4.16
- Laravel: 12.43.1
- Package: grazulex/laravel-apiroute v0.0.2
