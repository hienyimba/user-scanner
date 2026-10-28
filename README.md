# Laravel Scanner (Final User-Ready MVP)

This Laravel scanner MVP now supports:

## ✅ MVP outcomes
- Single username/email scans via web UI and API
- Connector registry loaded through manual + generated validators
- Persisted scan runs/results (file-backed run store)
- Results filtering and export (CSV/JSON)
- Async batch processing using queue jobs with visible progress polling

## Main components
- `ScannerEngineService`: executes validators with mode/category/module filtering
- `ProxyManagerService`: proxy parsing + rotation
- `ScanRunStore`: persistence layer for runs/results/progress/filter/export
- `RunScanJob`: queue worker unit per target for async batches
- Web UI at `/scanner`
- APIs:
  - `POST /api/scanner/run` (single immediate scan)
  - `GET /api/scanner/modules/{mode}`
  - `POST /api/scanner/runs` (create async batch run)
  - `GET /api/scanner/runs` (list runs)
  - `GET /api/scanner/runs/{runId}` (progress + filtered results)
  - `GET /api/scanner/runs/{runId}/export/{json|csv}`

## Operational notes
- Generated validators provide broad connector coverage, with best-effort parity for complex providers.
- Queue progress is visible in the UI through polling the run-status API.
- Run data is persisted at `laravel_app/storage/scan_runs.json`.
- Metadata enrichment and readiness workflow are documented in `docs/metadata_enrichment.md`.

Proxy rate limits are remembered in the shared cache by mode, module and proxy
endpoint across jobs and scan runs. Positive `Retry-After` values set the deadline,
including waits longer than a day. A 403, 429 or recognized site block without a
positive header uses `SCANNER_PROXY_MODULE_COOLDOWN_SECONDS` (300 seconds by
default). Shorter later responses and successes on other modules do not clear or
shorten an existing deadline. Other modules can continue using a healthy exit.

Pool acquisition, alternate-proxy retries and explicit proxy requests all enforce
these deadlines. When every requested exit is cooling down, the check returns
`Error` with `module_proxy_cooldown` and `retry_after_seconds` without making a
request or falling back to direct. This does not automatically reschedule a job.
Use a shared cache such as the default database store or Redis for worker-to-worker
persistence. Deploy the updated code, cache configuration as usual, and gracefully
restart queue workers so running processes load the new policy.
Run the offline cooldown regressions with
`php vendor/bin/phpunit tools/proxy-cooldown --no-progress`.

## Native browser transport

All 24 email definitions declaring `transport_profile` execute through the pinned
PHP FFI impersonation library. Explicit browser versions, request bodies, cookie
sessions and verdict rules are retained; generic `browser` and `chrome` profiles
resolve to the pinned default `chrome131`. The Python subprocess bridge and
its `curl_cffi` dependency have been removed. Historical Python source trees remain
inputs for metadata documentation; they are not an HTTP fallback.

Install dependencies normally so Composer applies the source-hash-checked native
compatibility patch. Enable FFI for each PHP worker or synchronous SAPI that runs
these email modules. A missing or unavailable FFI engine returns `Error` without
falling back to ordinary HTTP. `SCANNER_PHP_FFI_ENABLED=false` stops profiled
requests; it does not restore Python. `SCANNER_PHP_FFI_CA_BUNDLE` can name the
deployment's readable CA bundle.

Before deploying on Linux/PHP 8.4, run the native preflight and localhost acceptance
gate on that platform. The fixture servers, process orchestration and acceptance
workers all run in PHP; no Python runtime, packages or virtual environment are needed:

```sh
php8.4 -d ffi.enable=true artisan scanner:ffi-preflight
php8.4 tools/php-ffi/diagnostics-acceptance.php --php /usr/bin/php8.4 --output /tmp/scanner-ffi-diagnostics.json
php8.4 tools/php-ffi/acceptance.php --php /usr/bin/php8.4 --workers 4 --output /tmp/scanner-ffi-acceptance.json
```

After a passing gate, set `SCANNER_PHP_FFI_ENABLED=true`, configure the CA bundle
where required, and add `-d ffi.enable=true` to the affected workers' PHP command
lines. Cache configuration and gracefully restart those workers using the existing
deployment process. Keep worker counts, queue ordering and skip flags unchanged
unless a separate verified remediation changes them. If the release must be
rolled back, restore the previous application and dependency release together;
there is no runtime Python rollback switch in this release.

The 27 October report under `storage/app/ffi-production-20261027` records the
earlier four-module pilot. Its activation/rollback instructions are historical.
It is not evidence that the later migration passed a Linux/PHP 8.4 deployment gate.

CI uses PHP syntax checks, PHPUnit with coverage, and the dedicated localhost FFI
acceptance workflow. Run its offline tests with
`php vendor/bin/phpunit tools/php-ffi/validation-tests --no-progress`.
The regression classes and synthetic fixtures in that directory and
`tools/proxy-cooldown` are tracked release sources. The acceptance workflow runs
on relevant pushes to `main` and `codex/**`, pull requests, or manual dispatch.
It checks the tracked validation package, installs the locked dependencies, runs
the Linux/PHP 8.4 native preflight, both regression suites and both localhost
acceptance harnesses, and uploads their JSON and JUnit evidence even on failure.
CI removes the repository's legacy vendored files before installing from
`composer.lock`. Build production releases with a fresh dependency directory as
well; checked-in Composer metadata is not evidence that package files exist.
The general PHPUnit jobs run all tracked Unit, Feature, BrowserTransport and
ProxyCooldown tests. The fixtures cover current request flows, confirmed account
evidence, malformed responses and ambiguous results; they do not treat every HTTP
200 or password-policy rejection as an account verdict. Historical local reports
can include ignored suites from retired implementations and have different counts.

## Metadata readiness
- Normalized scanner results are exported with stable `status`, `profile_url`, `metadata`, `evidence`, and `confidence` fields.
- `php artisan scanner:metadata-readiness --json` reports documented June username / September email inventory capability separately from live-validated capability counts.
- `php artisan scanner:metadata-audit ...` verifies observed module output against the documented capability level.
- `php artisan scanner:metadata-validate-baselines ...` converts successful live audits into the validation overlay used by the readiness report.
- `docs/metadata_acceptance_audit.md` maps the WebVetted requirements to current evidence and calls out any remaining ambiguity.
