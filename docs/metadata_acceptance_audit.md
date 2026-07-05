# Metadata Acceptance Audit

This document maps the WebVetted-facing metadata requirements to current evidence in the repository. It is intentionally strict about the difference between documented capability and live-validated capability.

## Evidence model

- Documented capability: inferred from the June Python source inventory in `user-scanner-py-june-release/user_scanner`
- Validated capability: proven by successful live proxy-backed audits recorded in `config/scanner_metadata_validations.php`
- Normalized contract: proven by PHPUnit coverage over `ScanResult`, API responses, and stored-result rehydration
- Overlay stability: monitored by revalidation audits against stored validated targets

## Current readiness snapshot

From `php artisan scanner:metadata-readiness --json` in the current checkout:

- Documented modules: `293`
- Documented Level 3+: `189`
- Documented Level 4: `185`
- Validated modules: `113`
- Validated Level 3+: `112`
- Validated Level 4: `100`

For a machine-readable version of this audit, run:

```powershell
php artisan scanner:metadata-acceptance-audit --json
```

If you want CI to fail unless the 150 Level 3+ requirement is satisfied by live validation rather than documented capability, run:

```powershell
php artisan scanner:metadata-acceptance-audit --require-live-level3
```

If you want to check whether previously validated overlays have drifted, run:

```powershell
php artisan scanner:metadata-revalidate username --json
```

If you want the acceptance audit to enforce overlay stability as well as capability counts, first generate a revalidation report and then pass it back into the acceptance audit:

```powershell
php artisan scanner:metadata-revalidate username --output=storage/app/metadata-revalidation.json
php artisan scanner:metadata-acceptance-audit --revalidation-report=storage/app/metadata-revalidation.json --require-stable-overlays
```

Revalidation now distinguishes:

- `blocked`: every validated target failed with blocked, anti-bot, or rate-limited outcomes
- `inconclusive`: every validated target failed with infrastructure-shaped network or TLS errors
- `broken`: validated targets failed in ways that look like real parser or behavioral regressions rather than environmental access problems

## Requirement audit

### All modules return normalized JSON

Status: proved for the supported result states exercised by the application contract

Primary evidence:

- `tests/Unit/ScanResultNormalizationTest.php`
- `tests/Feature/MetadataEnrichmentTest.php`
- `tests/Feature/ScanApiNormalizationTest.php`
- `tests/Feature/PublicScanApiTest.php`
- `tests/Feature/ConcurrentCategoryScanTest.php`

What is proved:

- `found`, `not_found`, `error`, and `skipped` results serialize with stable normalized fields
- persisted legacy rows are rehydrated back into the normalized metadata shape
- public and internal scan APIs expose normalized payloads

### Blocked and rate-limited states are labeled correctly

Status: proved for the shared enrichment path and representative validator regressions

Primary evidence:

- `tests/Feature/MetadataEnrichmentTest.php`
- `tests/Feature/ScanApiNormalizationTest.php`
- `tests/Feature/UsernameValidatorRegressionTest.php`

What is proved:

- anti-bot and network failures normalize into explicit `status_detail` values
- representative validator regressions assert blocked/rate-limited wording instead of generic parse failures

### At least 250 modules have documented metadata capability

Status: proved

Primary evidence:

- `App\Services\Scanner\MetadataCapabilityService`
- `tests/Unit/MetadataCapabilityInventoryTest.php`
- `tests/Feature/MetadataAcceptanceAuditTest.php`
- `php artisan scanner:metadata-readiness --json`

Current value:

- `293` documented modules

### At least 150 modules return Level 3+ metadata for successful public username/profile checks

Status: partially proved, depending on how strictly "return" is interpreted

Primary evidence for documented capability:

- `App\Services\Scanner\MetadataCapabilityService`
- `tests/Unit/MetadataCapabilityInventoryTest.php`
- `tests/Feature/MetadataAcceptanceAuditTest.php`
- `php artisan scanner:metadata-readiness --json`

Current documented value:

- `189` modules classified at documented Level 3+

Primary evidence for live-validated capability:

- `config/scanner_metadata_validations.php`
- `php artisan scanner:metadata-readiness --json`

Current live-validated value:

- `112` modules validated at Level 3+

Interpretation note:

- If this requirement means documented source-derived capability, it is proved.
- If this requirement means 150 live-validated successful public checks, it is not yet proved.

### At least 50 modules return Level 4 metadata with evidence and confidence

Status: proved

Primary evidence:

- `config/scanner_metadata_validations.php`
- `App\Services\Scanner\MetadataCapabilityService`
- `tests/Unit/MetadataCapabilityInventoryTest.php`
- `tests/Feature/MetadataAcceptanceAuditTest.php`
- `php artisan scanner:metadata-readiness --json`

Current live-validated value:

- `100` modules validated at Level 4

### Metadata capability is classified systematically

Status: proved

Primary evidence:

- `App\Services\Scanner\MetadataCapabilityService`
- `tests/Unit/MetadataCapabilityInventoryTest.php`
- `tests/Feature/MetadataReadinessCommandTest.php`
- `tests/Feature/MetadataRevalidationCommandTest.php`

What is proved:

- every active June module is inventoried
- each active June module is assigned a documented capability level
- validated overlays are merged into the same inventory model
- validated overlays can be re-audited against their stored targets to detect drift

## Current conclusion

The repository now has:

- a stable normalized metadata contract
- source-derived documented capability inventory across active June modules
- explicit live-validated capability overlays
- readiness gates for documented Level 3+/4 and validated Level 3+/4
- enough live validation to prove the Level 4 acceptance threshold

The remaining ambiguity is scope, not implementation quality:

- if WebVetted requires 150 documented Level 3+ modules plus 50 live-validated Level 4 modules, the current state satisfies that bar
- if WebVetted requires 150 live-validated Level 3+ modules, that bar remains unproved in the current checkout
