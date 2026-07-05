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

## Metadata readiness
- Normalized scanner results are exported with stable `status`, `profile_url`, `metadata`, `evidence`, and `confidence` fields.
- `php artisan scanner:metadata-readiness --json` reports documented June-inventory capability separately from live-validated capability counts.
- `php artisan scanner:metadata-audit ...` verifies observed module output against the documented capability level.
- `php artisan scanner:metadata-validate-baselines ...` converts successful live audits into the validation overlay used by the readiness report.
- `docs/metadata_acceptance_audit.md` maps the WebVetted requirements to current evidence and calls out any remaining ambiguity.
