---
id: 2
title: 'Corrections post-tests v0.0.1 - Migration, Tracking, Fallback'
status: In Progress
priority: high
assignees:
  - '@claude'
labels:
  - bugfix
  - enhancement
  - tests
subtasks: []
dependencies: []
blocked_by: []
created_date: '2025-12-23T11:23:42.677Z'
updated_date: '2025-12-23T11:25:05.270Z'
changelog:
  - timestamp: '2025-12-23T11:23:42.677Z'
    action: created
    details: Task created
    user: system
  - timestamp: '2025-12-23T11:23:58.470Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T11:24:08.119Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T11:24:08.817Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T11:24:09.461Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T11:24:10.157Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T11:24:10.834Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T11:24:21.299Z'
    action: updated
    details: 'status: To Do → In Progress'
    user: user
  - timestamp: '2025-12-23T11:25:05.270Z'
    action: modified
    details: Task updated
    user: AI
acceptance_criteria:
  - text: La migration est publiée avec extension .php (pas .stub)
    checked: false
  - text: >-
      Le middleware TrackApiUsage est auto-enregistré quand
      tracking.enabled=true
    checked: false
  - text: 'ApiRoute::versions()->pluck(''name'') retourne les noms correctement'
    checked: false
  - text: Tous les tests unitaires passent (composer test)
    checked: false
  - text: Le CHANGELOG est mis à jour
    checked: false
ai_plan: >-
  ## Implementation Plan


  ### Objective

  Corriger les problèmes identifiés lors des tests d'intégration du package
  laravel-apiroute v0.0.1 et améliorer l'expérience développeur.


  ### Problèmes Identifiés

  1. **Migration .stub** - Le fichier de migration est publié avec extension
  .stub au lieu de .php

  2. **Tracking Middleware** - Le middleware TrackApiUsage n'est pas
  auto-enregistré dans le groupe api

  3. **Fallback Routes** - Le fallback ne fonctionne pas pour les routes
  manquantes dans une version supérieure

  4. **versions()->pluck()** - La méthode retourne des valeurs vides au lieu des
  noms de versions


  ### Steps

  1. Corriger la publication des migrations (renommer .stub en .php)

  2. Auto-enregistrer le middleware TrackApiUsage quand tracking.enabled est
  true

  3. Investiguer et corriger le bug versions()->pluck('name')

  4. Documenter le comportement du fallback (limitation connue ou à implémenter)

  5. Exécuter les tests unitaires pour valider les corrections

  6. Mettre à jour le CHANGELOG


  ### Files to Create/Modify

  - database/migrations/create_api_version_stats_table.php (renommer)

  - src/ApiRouteServiceProvider.php (auto-register tracking middleware)

  - src/ApiRouteManager.php (fix versions() collection)

  - CHANGELOG.md (documenter les fixes)


  ### Technical Approach

  - Renommer le fichier .stub directement sans passer par un stub

  - Utiliser le boot() du ServiceProvider pour enregistrer conditionnellement le
  middleware

  - Vérifier que la Collection retournée par versions() contient bien les objets
  VersionDefinition


  ### Edge Cases to Consider

  - Tracking désactivé: ne pas enregistrer le middleware

  - Base de données non migrée: gérer gracieusement l'erreur

  - Versions vides: retourner une collection vide proprement
ai_notes: >
  **2025-12-23T11:25:05.269Z** - ## Correction 1: Migration .stub -> .php


  Renommé le fichier:

  - Avant: create_api_version_stats_table.php.stub

  - Après: create_api_version_stats_table.php


  La migration sera maintenant publiée avec la bonne extension et pourra être
  exécutée directement avec 'php artisan migrate'.
---
Corrections identifiées lors des tests d'intégration: migration .stub, tracking middleware, fallback routes, versions()->pluck()
