# User Scanner Web Platform (Laravel Foundation)

This directory contains the **Phase A foundation scaffold** for the Laravel + Tailwind web platform described in `docs/WEB_APP_PRD.md`.

## Why scaffold-only?

The environment currently blocks fetching Composer packages from Packagist (`curl error 56 / CONNECT tunnel 403`), so a full `laravel/laravel` installation could not be bootstrapped automatically.

To keep momentum, this scaffold provides:
- Core domain models and enums for scans/connectors.
- Queue job + orchestration service contracts.
- Web/API route definitions.
- Request validation rules.
- Tailwind-oriented Blade templates for dashboard and scan flow.
- Database migrations for scans, results, and connectors.

## Next step when network access is available

1. Create a Laravel 11 app in this folder:
   ```bash
   composer create-project laravel/laravel .
   ```
2. Re-apply or copy in the files from this scaffold (`app/`, `routes/`, `resources/views/`, `database/migrations/`, `config/scanner.php`).
3. Install frontend deps and build assets:
   ```bash
   npm install
   npm run build
   ```
4. Run migrations and workers:
   ```bash
   php artisan migrate
   php artisan queue:work
   ```

## Scope covered from PRD

- FR-1 Dashboard (starter view + summary cards).
- FR-2 Single scan creation form + validation.
- FR-3 Connector abstraction and normalized result DTO.
- FR-4 Queue job for async scan batch execution.
- FR-5 Persistence schema for scan batches and item results.
- FR-6 Connector enable/disable model + admin-ready fields.

## Phase B implemented in this scaffold

- Full connector parity catalog implemented with Laravel/PHP connector classes generated from `user_scanner/user_scan` and `user_scanner/email_scan` modules.
- Connector registry now resolves connectors from config and supports module/category filtering.
- Retry + timeout policies applied per scan options.
- Bulk scan target support (`targets[]`) for web/API create requests.
- Scan cancellation support for queued/running batches.
- Results export support in JSON and CSV (web download + API response).


- No Python bridge is used; all registered connectors are implemented directly in Laravel/PHP dependencies.

- Phase B connector totals: 50 username connectors, 65 email connectors.


## Phase C performance hardening

- Connector class list is cached via Laravel cache to reduce per-request config hydration overhead.
- Dashboard statistics and recent scans are cached with a short TTL to improve p95 response time.
- Scan orchestration now batches progress writes (`processed_items`, `error_count`) and polls cancel state periodically instead of every connector.
- Added DB indexes for common dashboard/results filters and sorting patterns.
