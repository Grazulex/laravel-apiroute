---
id: 3
title: Fix issues identified in test report
status: Done
priority: high
assignees:
  - '@claude'
labels:
  - bugfix
  - improvement
subtasks: []
dependencies: []
blocked_by: []
created_date: '2025-12-23T12:06:50.283Z'
updated_date: '2025-12-23T12:15:40.329Z'
closed_date: '2025-12-23T12:15:40.329Z'
changelog:
  - timestamp: '2025-12-23T12:06:50.283Z'
    action: created
    details: Task created
    user: system
  - timestamp: '2025-12-23T12:07:32.414Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T12:07:40.577Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T12:07:41.606Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T12:07:42.623Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T12:07:43.639Z'
    action: updated
    details: 'status: To Do → In Progress'
    user: user
  - timestamp: '2025-12-23T12:08:07.120Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T12:08:44.469Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T12:09:54.819Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T12:10:39.868Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T12:15:08.473Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T12:15:22.012Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T12:15:30.266Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T12:15:31.293Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T12:15:32.300Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T12:15:40.329Z'
    action: updated
    details: 'status: In Progress → Done'
    user: user
acceptance_criteria:
  - text: 'JSON output de api:status --json ne contient plus d''artefacts HTML'
    checked: true
  - text: Link header contient le path complet de l'API
    checked: true
  - text: Tous les tests passent
    checked: true
ai_plan: >-
  ## Plan d'implémentation


  ### Objectif

  Corriger les problèmes identifiés lors des tests d'intégration du package.


  ### Issues identifiées

  1. **JSON Output Bug**: La commande `api:status --json` contient des artefacts
  HTML échappés (`<\/>`)

  2. **Link Header incomplet**: L'URL du successor ne contient pas le chemin
  complet de l'API


  ### Étapes

  1. Analyser le code de la commande ApiStatusCommand pour identifier la source
  des artefacts HTML

  2. Corriger le formatage du status dans l'output JSON

  3. Analyser le code VersionHeaders pour améliorer le Link header

  4. Améliorer la génération du Link header avec le path complet

  5. Exécuter les tests pour vérifier les corrections

  6. Mettre à jour la documentation si nécessaire


  ### Fichiers concernés

  - src/Commands/ApiStatusCommand.php (modify)

  - src/Http/Headers/VersionHeaders.php (modify)

  - tests/ (vérification)


  ### Approche technique

  - Supprimer les tags de couleur/formatage dans le JSON output

  - Utiliser le path de la request courante pour construire le Link header
  complet
ai_notes: >
  **2025-12-23T12:08:07.120Z** - **13:08** - FOUND: Bug dans
  ApiStatusCommand.php ligne 54 - formatStatus() retourne des tags ANSI
  (<fg=red>sunset</>) qui sont inclus dans le JSON output

  **2025-12-23T12:08:44.468Z** - **13:10** - FOUND: Bug dans VersionHeaders.php
  - buildSuccessorUrl() ne prend pas en compte le path de la request actuelle,
  le Link header devrait contenir le path complet

  **2025-12-23T12:09:54.818Z** - **13:12** - PROGRESS: Correction
  ApiStatusCommand terminée - ajout de formatStatusRaw() pour le JSON output

  **2025-12-23T12:10:39.868Z** - **13:14** - PROGRESS: Correction VersionHeaders
  terminée - buildSuccessorUrl() utilise maintenant le path complet de la
  request

  **2025-12-23T12:15:08.472Z** - **13:18** - PROGRESS: Tous les tests passent -
  Pest: 53 tests, Pint: OK, PHPStan: OK
ai_review: >-
  ## Self-Review


  ### Complété

  - [x] Fix JSON output HTML artifacts dans ApiStatusCommand
    - Ajout de formatStatusRaw() pour retourner le status sans tags ANSI
    - Utilisation de cette méthode quand --json est passé
  - [x] Amélioration du Link header avec le path complet
    - Modification de buildSuccessorUrl() pour utiliser le path de la request
    - Remplacement de la version courante par le successor dans le path
    - Passage de la Request à addToResponse()

  ### Tests effectués

  - Pest tests: 53 tests passés (129 assertions)

  - Pint lint: OK (47 fichiers)

  - PHPStan level 8: Aucune erreur


  ### Fichiers modifiés

  - src/Commands/ApiStatusCommand.php

  - src/Http/Headers/VersionHeaders.php

  - src/Middleware/ResolveApiVersion.php


  ### Limitations connues

  - Aucune


  ### Notes

  Les corrections sont rétrocompatibles car le paramètre Request dans
  addToResponse() est optionnel avec une valeur par défaut de null.
---

