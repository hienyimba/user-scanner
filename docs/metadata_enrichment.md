# Metadata Enrichment Layer

This repository now exposes a normalized metadata contract on top of the existing scanner result model. The goal is to make username and email modules behave consistently enough for WebVetted-style downstream consumers without changing the public scan APIs.

For a requirement-by-requirement evidence map, see `docs/metadata_acceptance_audit.md`.

## Source of truth

- Python inventory source: `user-scanner-py-june-release/user_scanner`
- Inventory scope: active modules under `user_scan/` and `email_scan/`
- Explicitly excluded: any module under `abandoned/`

`App\Services\Scanner\MetadataCapabilityService` reads that June inventory directly and classifies each active module into a documented capability level.

Important distinction:

- Documented capability counts come from static June-source analysis.
- Validated capability counts come from successful live proxy-backed audits recorded in `config/scanner_metadata_validations.php`.
- Treat documented capability as expected coverage and validated capability as proven reproducible coverage.

## Normalized result contract

`App\DTO\ScanResult::toNormalizedArray()` exposes the stable payload shape used by API responses, exports, and persisted-result rehydration:

```json
{
  "target": "kaifcodec",
  "category": "dev",
  "mode": "username",
  "platform": "github",
  "url": "https://github.com/kaifcodec",
  "status": "found",
  "status_detail": null,
  "confidence": 0.95,
  "profile_url": "https://github.com/kaifcodec",
  "metadata_level": 4,
  "metadata": {
    "display_name": "kaifcodec",
    "username": "kaifcodec",
    "avatar_url": null,
    "bio": null,
    "location": null,
    "website_url": null,
    "public_email": null,
    "followers": null,
    "following": null,
    "posts_count": null,
    "created_at": null,
    "last_active_at": null,
    "account_type": null,
    "is_verified": null,
    "is_private": null,
    "external_links": [],
    "evidence": [
      "profile_url"
    ],
    "observed_metadata_level": 4
  },
  "evidence": [
    "profile_url"
  ],
  "error": null
}
```

Notes:

- `status` is normalized to `found`, `not_found`, `error`, or `skipped`.
- `profile_url` is resolved conservatively from explicit validator output and known profile/account URLs.
- `metadata` is always present and normalized, even for stored legacy results that predate the enrichment layer.
- public-facing metadata values are sanitized before they affect evidence or confidence:
  `profile_url`, `avatar_url`, `website_url`, and `external_links` must be public `http`/`https` URLs, and `public_email` must be a valid extracted email address.
- `public_email` is only populated when it was actually extracted from public evidence. The queried email itself is not treated as public metadata.
- `confidence` is derived when a validator does not set it directly.
- `evidence` is a flattened list of signals that support the returned metadata.
- Shared profile HTML enrichment now understands OpenGraph, JSON-LD, semantic HTML itemprops, common hydration JSON payloads, JavaScript variable and dotted-object assignments, JSON embedded in `data-*` attributes, link-object arrays inside hydration payloads, basic follower/following/post stats, and joined/updated date hints.

## Metadata capability levels

Documented capability levels are inventory-level expectations derived from the June Python source:

- Level 0: broken or unsupported
- Level 1: found/not-found only
- Level 2: found plus profile/account URL
- Level 3: basic public metadata
- Level 4: rich public metadata plus evidence and confidence

Observed capability is attached to each successful result as `metadata.observed_metadata_level`, while documented capability is tracked centrally in the inventory service and validation overlay.

## Console workflow

### Readiness summary

Use this to check the repo against the project-wide acceptance thresholds:

```powershell
php artisan scanner:metadata-readiness --json
```

Current live snapshot in this checkout:

- Documented modules: `293`
- Documented level 3+: `189`
- Documented level 4: `185`
- Validated modules: `113`
- Validated level 3+: `112`
- Validated level 4: `100`
- Username documented: `188`
- Email documented: `105`

Threshold flags:

- `--min-documented`
- `--min-level3`
- `--min-level4`
- `--min-validated`
- `--min-validated-level3`
- `--min-validated-level4`
- `--output`
- `--json`

### Live audit of normalized output

Use this to verify what a module actually returns for one or more targets:

```powershell
php artisan scanner:metadata-audit username kaifcodec --module=github --json
```

Useful flags:

- `--module=*`
- `--category=`
- `--only-found`
- `--use-proxy`
- `--no-proxy`
- `--proxy=`
- `--enrich-metadata=1`
- `--min-level3`
- `--min-level4`
- `--max-found-below-documented`

Proxy note:

- `--proxy=` accepts either a full proxy URL or a bare configured pool entry such as `disp.oxylabs.io:8007`.
- Bare configured pool entries are resolved through `ProxyManagerService`, so configured Oxylabs credentials are injected automatically for targeted fallback audits.

### Baseline validation overlay

Use this to convert successful live audits into a conservative validation overlay:

```powershell
php artisan scanner:metadata-validate-baselines username --module=github --json
```

Supporting config:

- Validation targets: `config/scanner_metadata_targets.php`
- Validation overlay: `config/scanner_metadata_validations.php`

The exported validation level is the minimum observed level across successful targets for a module.

### Revalidate existing overlays

Use this to check whether modules that already have validated overlays still reproduce against their stored validated targets:

```powershell
php artisan scanner:metadata-revalidate username --json
```

Useful flags:

- `--module=*`
- `--use-proxy`
- `--no-proxy`
- `--proxy=`
- `--max-unstable`
- `--max-degraded`
- `--max-blocked`
- `--max-inconclusive`
- `--max-broken`

You can feed that report into the acceptance audit when you want a stricter production-readiness gate:

```powershell
php artisan scanner:metadata-acceptance-audit --revalidation-report=storage/app/metadata-revalidation.json --require-stable-overlays
```

## Where normalization happens

- `App\DTO\ScanResult`: stable output schema and confidence derivation
- `App\Services\Scanner\MetadataEnrichmentService`: enrichment pipeline used by scanner execution
- `App\Services\Scanner\ProfileMetadataExtractor`: generic profile HTML / JSON-LD / OpenGraph extraction
- `App\Services\Scanner\MetadataCapabilityService`: June-backed documented capability inventory
- `App\Services\Scanner\MetadataAuditService`: target-level audit reports for actual output
- `App\Services\Scanner\MetadataBaselineValidationService`: baseline validation and overlay generation
- `App\Services\Scanner\MetadataRevalidationService`: overlay drift detection against validated targets
- `App\Services\Scanner\ScanRunStore`: persisted-result rehydration through the enrichment layer

## Tests that guard this layer

- `tests/Unit/ScanResultNormalizationTest.php`
- `tests/Feature/MetadataEnrichmentTest.php`
- `tests/Feature/ScanApiNormalizationTest.php`
- `tests/Feature/PublicScanApiTest.php`
- `tests/Feature/ConcurrentCategoryScanTest.php`
- `tests/Feature/MetadataReadinessCommandTest.php`
- `tests/Feature/MetadataRevalidationCommandTest.php`
- `tests/Unit/MetadataCapabilityInventoryTest.php`

These tests cover schema stability, stored-result normalization, readiness thresholds, and documented inventory counts from the June source.
