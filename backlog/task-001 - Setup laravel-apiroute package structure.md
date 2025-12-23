---
id: 1
title: Setup laravel-apiroute package structure
status: In Progress
priority: high
assignees:
  - '@claude'
labels:
  - setup
  - package
  - laravel
subtasks: []
dependencies: []
blocked_by: []
created_date: '2025-12-23T09:26:05.632Z'
updated_date: '2025-12-23T09:26:51.897Z'
changelog:
  - timestamp: '2025-12-23T09:26:05.632Z'
    action: created
    details: Task created
    user: system
  - timestamp: '2025-12-23T09:26:26.846Z'
    action: modified
    details: Task updated
    user: AI
  - timestamp: '2025-12-23T09:26:36.423Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T09:26:37.082Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T09:26:37.747Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T09:26:38.443Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T09:26:39.107Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T09:26:39.778Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T09:26:40.431Z'
    action: modified
    details: Task updated
    user: user
  - timestamp: '2025-12-23T09:26:51.897Z'
    action: updated
    details: 'status: To Do → In Progress'
    user: user
acceptance_criteria:
  - text: composer.json valid with correct dependencies
    checked: false
  - text: PHPStan level max passes
    checked: false
  - text: Laravel Pint passes
    checked: false
  - text: All GitHub workflows configured
    checked: false
  - text: All MD documentation files present
    checked: false
  - text: Basic ServiceProvider and Facade work
    checked: false
  - text: Tests run successfully with Pest
    checked: false
ai_plan: >-
  ## Implementation Plan


  ### Objective

  Setup complete package structure for laravel-apiroute following Grazulex
  standards (identical to laravel-chronotrace, laravel-atlas,
  laravel-devtoolbox)


  ### Files to Create


  **Root Configuration Files:**

  1. composer.json - Package definition for PHP 8.3+, Laravel 12

  2. phpstan.neon - Static analysis (level max with Larastan)

  3. pint.json - Laravel code style preset

  4. rector.php - PHP 8.3 refactoring rules

  5. phpunit.xml - Test configuration with Pest

  6. testbench.yaml - Orchestra Testbench config

  7. .gitignore - Standard Laravel package ignores

  8. .editorconfig - Editor configuration


  **Documentation Files:**

  9. README.md - Complete package documentation

  10. LICENSE.md - MIT License

  11. CONTRIBUTING.md - Contribution guidelines

  12. CODE_OF_CONDUCT.md - Contributor Covenant v2.0

  13. SECURITY.md - Security policy

  14. RELEASES.md - Version history (initial)

  15. CHANGELOG.md - Changelog (initial)


  **GitHub Workflows:**

  16. .github/workflows/tests.yml - Pest tests

  17. .github/workflows/static-analysis.yml - PHPStan

  18. .github/workflows/code-style.yml - Laravel Pint


  **Package Structure:**

  19. src/ApiRouteServiceProvider.php - Main service provider

  20. src/Facades/ApiRoute.php - Facade

  21. config/apiroute.php - Package configuration

  22. tests/TestCase.php - Base test case

  23. tests/Pest.php - Pest configuration

  24. tests/ArchTest.php - Architecture tests


  ### Technical Approach

  - Exact same structure as laravel-chronotrace

  - PHP 8.3+ requirement (not 8.1 as in spec)

  - Laravel 12.x only (not 10/11 as in spec)

  - Orchestra Testbench 10.x

  - Pest 3.x for testing

  - PHPStan level max with Larastan


  ### Quality Standards

  - PHPStan level max

  - Laravel Pint with Laravel preset

  - Rector for PHP 8.3 upgrades

  - 100% test coverage target
---
Initialize package structure with composer.json, quality tools, GitHub workflows, MD files - PHP 8.3+ Laravel 12
