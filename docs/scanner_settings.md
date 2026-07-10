# Scanner Settings Guide

This guide explains each `SCANNER_*` environment setting used by the app, the recommended value, and why that value is a good default.

After changing `.env`, refresh config and restart workers so long-running queue processes pick up the new settings:

```bash
php artisan config:clear
php artisan queue:restart
```

If production uses cached config, run the app's normal `config:cache` step after clearing.

## Recommended Production Baseline

These values are tuned for external API use where we want stable completion, controlled p95 latency, and fewer proxy-capacity failures.

```dotenv
SCANNER_ASYNC_QUEUE=scanner
SCANNER_ASYNC_JOB_TIMEOUT=60
SCANNER_ASYNC_JOB_TRIES=1
SCANNER_HTTP_CONNECT_TIMEOUT_SECONDS=3

SCANNER_PROXY_PROVIDER=oxylabs_isp
SCANNER_PROXY_MAX_CONCURRENT_PER_IP=2
SCANNER_PROXY_MAX_RETRY_PER_MODULE=1
SCANNER_PROXY_RETRY_TIMEOUT_FAILURES=false
SCANNER_PROXY_FAILURE_THRESHOLD=2
SCANNER_PROXY_COOLDOWN_MIN_SECONDS=30
SCANNER_PROXY_COOLDOWN_MAX_SECONDS=90
SCANNER_PROXY_WAIT_TIMEOUT_SECONDS=15
SCANNER_PROXY_WAIT_RETRY_SECONDS=2

SCANNER_METADATA_FETCH_PROFILE_HTML=true
SCANNER_METADATA_TIMEOUT_SECONDS=8
SCANNER_METADATA_MAX_HTML_BYTES=262144
SCANNER_METADATA_MAX_EXTERNAL_LINKS=10
```

Do not fix proxy-capacity errors by only increasing `SCANNER_PROXY_WAIT_TIMEOUT_SECONDS`. That usually raises p95 and can push jobs into queue timeouts. First match queue worker concurrency to proxy capacity, skip failing modules, and keep timeout retries disabled.

## General HTTP Settings

| Setting | What it does | Recommended | Why |
| --- | --- | --- | --- |
| `SCANNER_USER_AGENT` | Default User-Agent used by generated validators that do not set their own headers. | Leave unset or use a current desktop Chrome-like UA. | A realistic UA reduces avoidable bot blocks. Leaving it unset uses the app default from `config/scanner.php`. |
| `SCANNER_VERIFY_SSL` | Controls TLS certificate verification for scanner HTTP requests. | `false` for this broad scanner; `true` only after validating all important modules. | Many targets and proxy paths can have TLS quirks. `false` favors scanner reliability; `true` is stricter but may create false module errors. |
| `SCANNER_HTTP_CONNECT_TIMEOUT_SECONDS` | Global connection timeout applied to Laravel HTTP/Guzzle requests. | `3` | Fails fast when a proxy or host cannot establish a connection, preventing slow connection hangs from inflating p95. |

## Async And Queue Settings

| Setting | What it does | Recommended | Why |
| --- | --- | --- | --- |
| `SCANNER_ASYNC_QUEUE` | Queue name used for scanner validator jobs. | `scanner` in production; `default` is acceptable locally. | A dedicated queue lets scanner workers be scaled and restarted without mixing with unrelated app jobs. |
| `SCANNER_ASYNC_JOB_TIMEOUT` | Laravel job timeout for one validator job. | `60` | Gives enough room for proxy lease wait plus one validator request and metadata enrichment, while still killing truly stuck jobs. Worker `--timeout` should be higher than this, usually by 10-15 seconds. |
| `SCANNER_ASYNC_JOB_TRIES` | Number of times Laravel retries an entire validator job. | `1` | Whole-job retries multiply traffic, proxy pressure, and duplicate failure risk. Module-level proxy retry is already handled separately. |
| `SCANNER_SYNC_RESULT_THRESHOLD` | Web controller threshold for deciding whether a planned scan should be async instead of immediate. | `12` | Small scans can feel instant; larger scans should go through the queue so the UI/API can poll progress. |
| `SCANNER_MAX_EXPECTED_RESULTS` | Hard cap on the number of validator jobs a run can enqueue. | `5000` | Prevents accidental huge scans from flooding the database, queue, and proxy pool. Lower this if the app is primarily public/API-facing. |
| `SCANNER_POLL_INTERVAL_MS` | Suggested frontend polling interval for run progress. | `2000` | Two seconds is responsive without hammering the app/database. Use a higher value if many clients poll concurrently. |

## Proxy Settings

| Setting | What it does | Recommended | Why |
| --- | --- | --- | --- |
| `SCANNER_PROXY_PROVIDER` | Label for the proxy provider/config strategy. | `oxylabs_isp` | Matches the structured pool currently configured in `config/scanner.php`. |
| `SCANNER_PROXY_USERNAME` | Proxy username added to configured pool proxies. | Set in production; leave blank only when proxies do not need auth. | Required for authenticated provider proxies. Do not commit real values. |
| `SCANNER_PROXY_PASSWORD` | Proxy password added to configured pool proxies. | Set in production; leave blank only when proxies do not need auth. | Required for authenticated provider proxies. Do not commit real values. |
| `SCANNER_PROXY_LIST` | Optional explicit proxy list. If non-empty, it replaces the structured pool from config. | Leave blank for normal production. | The built-in structured pool includes tier metadata. Use this only for emergency overrides or diagnostics. |
| `SCANNER_PROXY_MAX_CONCURRENT_PER_IP` | Maximum simultaneous leased jobs per proxy endpoint. | `2` | With 20 proxies, this gives about 40 concurrent leases. Setting `1` is safer but can cause "No proxy capacity available" unless worker concurrency is also low. |
| `SCANNER_PROXY_MAX_RETRY_PER_MODULE` | Maximum alternate-proxy retry attempts for retryable proxy-shaped failures. | `1` normally; `0` during incidents. | One retry can recover from a bad proxy or WAF block. More retries inflate p95 and drain capacity. |
| `SCANNER_PROXY_RETRY_TIMEOUT_FAILURES` | Whether timeout-shaped failures should consume another proxy retry. | `false` | Timeouts usually mean slow target/proxy behavior. Retrying them doubles latency and was a contributor to queue timeouts. |
| `SCANNER_PROXY_FAILURE_THRESHOLD` | Number of retryable failures before a proxy is cooled down. | `2` | Removes suspicious proxies quickly without cooling them down on a single noisy request. Use `3` only if capacity is tight and the provider is stable. |
| `SCANNER_PROXY_COOLDOWN_MIN_SECONDS` | Minimum cooldown duration after a proxy hits the failure threshold. | `30` | Gives a bad proxy time to recover without removing capacity for too long. |
| `SCANNER_PROXY_COOLDOWN_MAX_SECONDS` | Maximum cooldown duration after a proxy hits the failure threshold. | `90` | Adds jitter so failed proxies do not all re-enter at once. Avoid very high values unless you also reduce worker concurrency. |
| `SCANNER_PROXY_WAIT_TIMEOUT_SECONDS` | How long a job waits for an available proxy lease before returning a capacity error. | `15` | Long enough to absorb brief contention, short enough to protect p95 and job timeout. |
| `SCANNER_PROXY_WAIT_RETRY_SECONDS` | Sleep interval between proxy lease attempts while waiting for capacity. | `2` | Keeps lock/database/cache pressure low while still checking often enough. |

### Proxy Capacity Rule Of Thumb

Approximate lease capacity is:

```text
enabled proxies * SCANNER_PROXY_MAX_CONCURRENT_PER_IP
```

If the number of active queue workers is higher than this, jobs can hit "No proxy capacity available from configured pool" even when the proxies are healthy. Cooldowns reduce capacity further. For example, 20 proxies with `SCANNER_PROXY_MAX_CONCURRENT_PER_IP=1` gives roughly 20 active leases, before cooldowns.

For production API traffic, prefer:

| Situation | Suggested adjustment |
| --- | --- |
| Frequent capacity errors | Use `SCANNER_PROXY_MAX_CONCURRENT_PER_IP=2`, shorten cooldowns to `30-90`, and reduce queue workers if workers exceed proxy capacity. |
| Many timeout errors | Keep `SCANNER_PROXY_RETRY_TIMEOUT_FAILURES=false`; do not increase retries. |
| Many 403/429/WAF errors | Use ops skip flags for the worst modules, keep one proxy retry, and inspect whether the module is broken. |
| Need maximum caution with provider reputation | Use `SCANNER_PROXY_MAX_CONCURRENT_PER_IP=1`, but reduce worker count and accept lower throughput. |

## Metadata Settings

| Setting | What it does | Recommended | Why |
| --- | --- | --- | --- |
| `SCANNER_METADATA_FETCH_PROFILE_HTML` | Enables follow-up metadata enrichment for found profiles where supported. | `true` | Improves final result quality for WebVetted-style output. Turn off only during performance incidents. |
| `SCANNER_METADATA_TIMEOUT_SECONDS` | Timeout for metadata enrichment HTTP requests. | `8` | Metadata is valuable but should not dominate job runtime. |
| `SCANNER_METADATA_MAX_HTML_BYTES` | Maximum profile HTML bytes parsed for metadata. | `262144` | 256 KB captures useful page metadata while avoiding excessive memory and parsing time. |
| `SCANNER_METADATA_MAX_EXTERNAL_LINKS` | Maximum external links extracted into metadata. | `10` | Keeps result payloads useful without becoming noisy or large. |

## Metadata Baseline And Audit Settings

These settings are for metadata validation commands and readiness audits. They should use controlled test accounts/inboxes, not customer data.

| Setting | What it does | Recommended | Why |
| --- | --- | --- | --- |
| `SCANNER_EMAIL_BASELINE_PRIMARY` | Email address behind the `baseline_email_primary` alias. | Use a controlled test email; blank outside audit environments. | Lets audit commands validate modules without hard-coding private emails in the repo. |
| `SCANNER_EMAIL_BASELINE_SECONDARY` | Email address behind the `baseline_email_secondary` alias. | Use a second controlled test email; blank if unused. | Some modules need more than one known email state for validation. |
| `SCANNER_EMAIL_BASELINE_TERTIARY` | Email address behind the `baseline_email_tertiary` alias. | Use a third controlled test email; blank if unused. | Covers modules that require a different account state or provider coverage. |
| `SCANNER_EMAIL_BASELINE_TARGET_ALIASES` | JSON object mapping custom aliases to real email addresses. | Keep unset unless adding private audit aliases. | Extends baseline aliases without editing code. Example: `{"custom_alias":"audit@example.com"}`. |
| `SCANNER_EMAIL_BASELINE_MODULE_TARGETS` | JSON object mapping email module keys to audit target aliases or direct emails. | Keep unset unless overriding module audit targets. | Lets a specific module use different known-good audit targets. Example: `{"github":["baseline_email_primary"]}`. |

## Incident Presets

Use these as quick starting points during operational incidents.

### Capacity Errors

```dotenv
SCANNER_PROXY_MAX_CONCURRENT_PER_IP=2
SCANNER_PROXY_COOLDOWN_MIN_SECONDS=30
SCANNER_PROXY_COOLDOWN_MAX_SECONDS=90
SCANNER_PROXY_WAIT_TIMEOUT_SECONDS=15
SCANNER_PROXY_RETRY_TIMEOUT_FAILURES=false
```

Also reduce queue worker count if active workers exceed proxy lease capacity.

### High p95 / Queue Timeouts

```dotenv
SCANNER_PROXY_MAX_RETRY_PER_MODULE=0
SCANNER_PROXY_RETRY_TIMEOUT_FAILURES=false
SCANNER_HTTP_CONNECT_TIMEOUT_SECONDS=3
SCANNER_METADATA_TIMEOUT_SECONDS=6
```

Then use ops skip flags for the modules with the highest error rate.

### Metadata Too Slow

```dotenv
SCANNER_METADATA_FETCH_PROFILE_HTML=false
```

This should be temporary. It improves speed but reduces final result richness.
