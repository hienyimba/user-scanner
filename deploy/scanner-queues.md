# Scanner queue separation and bounded fair scheduling

## What changes

Email jobs use `scanner-email`; username jobs use `scanner-username`. The
Supervisor profile in `deploy/supervisor/user-scanner.conf` reserves five workers
for email and five for usernames, with ten shared workers (20 total) on the
8 GB / 4 dedicated vCPU server. Shared workers prefer email and otherwise process
usernames. A mode can use up to 15 workers when alone; neither can consume the
other's five reserved workers. This is an initial sizing profile, not a measured
production capacity guarantee. The pools still share the database, CPU, memory
and proxy pool. Under sustained email traffic usernames retain five workers,
not their former exclusive fifteen; the tradeoff is protected email latency.

Each username run can have at most 15 dispatched unfinished jobs; each email run
can have at most 15. Remaining task descriptors are durably staged in
`scan_run_tasks`. Completing or terminally failing a job dispatches a replacement
at the queue tail. New scans therefore wait behind bounded windows from existing
scans, rather than thousands of their jobs. This is bounded FIFO fairness, not
strict equal CPU time or an SLA. A flood of new scans can still overload the host.

All validator/target pairs are still checked; selected modules, skip flags,
target delays and result ordering are preserved. No module is silently dropped.
The configured maximum remains 5,000 results per run. This change does not reduce
the HTTP work in a full scan or guarantee the former 20-second p95.

Task staging, initial dispatch and run creation commit together. Result writes,
task completion and replacement dispatch also commit together. The scheduler
requires the Laravel database queue and scan tables on the **same named database
connection**. It explicitly inserts queue rows before commit, even when the
connection has `after_commit=true`. No cron dispatcher or Redis migration is
needed. Completed task rows are removed; deleting a run cascades its task rows.
Completed job redelivery is ignored. As with any remote request, a worker dying
after an HTTP request but before its result commits cannot guarantee exactly-once
remote execution. Keep the timeout/retry relationship below to avoid overlapping
deliveries. Do not purge the jobs table: it would strand dispatched task slots.

Ops snapshots aggregate both new queues plus the configured legacy queue, and
include undispatched staged tasks in `queued_jobs`. Per-run counters retain their
existing meaning: queued means all work not yet started, including staged tasks.

## Required production environment

```dotenv
QUEUE_CONNECTION=database
SCANNER_QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=120
SCANNER_ASYNC_QUEUE=scanner
SCANNER_EMAIL_QUEUE=scanner-email
SCANNER_USERNAME_QUEUE=scanner-username
SCANNER_FAIR_SCHEDULING=true
SCANNER_EMAIL_RUN_WINDOW=15
SCANNER_USERNAME_RUN_WINDOW=15
SCANNER_ASYNC_JOB_TIMEOUT=60
SCANNER_ASYNC_JOB_TRIES=1
```

`DB_QUEUE_CONNECTION` must be unset or match `DB_CONNECTION` exactly. This
implementation does not support fair scheduling with a separate queue database,
Redis, SQS or failover connections. The sync driver remains synchronous for local
use; it bypasses staging. `SCANNER_FAIR_SCHEDULING=false` disables staging only for
NEW runs, while preserving mode routing. Already staged runs continue to drain.
Do not change queue/database connection names while staged runs remain active.

The per-job 60-second timeout overrides Supervisor's 90-second fallback.
`retry_after=120` exceeds both; `stopwaitsecs=180` permits graceful shutdown.
`--memory=256` is a PHP-memory recycle threshold, not a reserved allocation or
hard RSS cap. `--max-jobs=500 --max-time=3600` recycles less frequently than the
previous 100-job setting, while retaining memory and time safeguards.

## Safe cutover (manual production steps; not executed by this change)

1. Back up the database, current environment and Supervisor config. Confirm the
   host has PHP 8.4 CLI with `pcntl` enabled for job timeout enforcement.
2. Stop NEW scan submissions, including API/client traffic. `artisan down`
   alone is not sufficient if old workers then pause: existing workers should
   continue draining using `--force` during maintenance, or block scan ingress
   at the reverse proxy while keeping the app out of maintenance mode.
3. Let the old workers drain the existing `scanner` queue AND let all active
   runs finish. Do not clear queues or mark runs complete manually. Check:

   ```sql
   SELECT queue, COUNT(*) AS jobs FROM jobs GROUP BY queue;
   SELECT status, COUNT(*) AS runs FROM scan_runs
   WHERE status IN ('queued', 'running') GROUP BY status;
   ```

4. Stop the old group after draining: `sudo supervisorctl stop 'user-scanner:*'`.
   Resize to the 8 GB plan using your normal Linode maintenance procedure while
   scan ingress is still blocked. Prevent the old Supervisor pool from starting
   automatically after a resize reboot (or stop it again before proceeding).
   Deploy the updated code, including the new migration and test. Update `.env`
   with the settings above, leaving existing proxy credentials unchanged.
5. From `/var/www/user-scanner`, run as your normal application deployment user:

   ```sh
   php8.4 artisan migrate --force
   php8.4 artisan config:cache
   php8.4 artisan schedule:list
   ```

6. Replace the existing `/etc/supervisor/conf.d/user-scanner.conf` contents with
   `deploy/supervisor/user-scanner.conf`. If the old program lives in a differently
   named file, replace that definition rather than leaving both configurations.

   ```sh
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl status
   ```

   Verify exactly five `user-scanner-email`, five `user-scanner-username` and ten
   `user-scanner-shared` processes, and no old `user-scanner` pool. The new pools
   autostart on update. Username/shared workers also consume legacy `scanner`
   jobs as a compatibility fallback; this is not a substitute for draining
   before cutover.
7. Reopen scan ingress. Submit approved representative username/email scans,
   including overlapping large and small runs. Confirm results finish, queue
   names are correct, per-run dispatched task counts stay within their windows,
   and completed runs have no task rows left. Inspect backlog with:

   ```sql
   SELECT queue, COUNT(*) AS jobs FROM jobs GROUP BY queue;
   SELECT queue,
          SUM(CASE WHEN dispatched_at IS NULL THEN 1 ELSE 0 END) AS staged,
          SUM(CASE WHEN dispatched_at IS NOT NULL THEN 1 ELSE 0 END) AS dispatched
   FROM scan_run_tasks GROUP BY queue;
   SELECT scan_run_id, queue, COUNT(*) AS dispatched
   FROM scan_run_tasks WHERE dispatched_at IS NOT NULL
   GROUP BY scan_run_id, queue HAVING COUNT(*) > 15;
   ```

   The final query should return no rows with the recommended 15-job windows.

   `php8.4 artisan scanner:ops-snapshot` captures the combined backlog. Keep the
   existing Laravel scheduler running. Monitor CPU, `MemAvailable`, swap-in/out,
   worker RSS, database lock waits and proxy capacity errors under real load.
   Compare p95 over a NEW deployment window, separately for username and email;
   the dashboard's historical mixed-mode p95 will not immediately reset.

## Rollback and capacity limits

For a scheduling-only rollback, set `SCANNER_FAIR_SCHEDULING=false`, cache config
and gracefully restart workers. Keep the new code, tables and all three worker pools
until existing staged scans finish. Eager dispatch still routes by mode. For a
full code rollback, block ingress and drain ALL new queues and task rows first;
old code cannot replenish staged work. Do not drop the task table while active.

Twenty workers fit below the configured 60-proxy / one-lease-per-proxy ceiling
before cooldowns; cooldowns can still reduce capacity below 20. Do not add workers
or increase per-IP concurrency blindly. If memory/swap, CPU or database pressure
appears, reduce the shared pool (e.g. 10 to 5), keeping the five reserved workers
per mode, and reduce the per-run windows to 10 to match isolated capacity.
This lowers concurrency but does not cure excess incoming load. Benchmark before
increasing beyond this initial profile.

The proxy inventory was expanded on 2026-10-22 to 60 endpoints (56 US, 4 GB),
including ports 8031-8060. Existing credentials and primary/fallback assignments
are unchanged: ports 8020 and 8019 remain fallback; 8007 and 8006 remain primary.
A non-empty `SCANNER_PROXY_LIST` overrides the configured pool:
remove an obsolete override or update it to include the intended endpoints.
After deploying the config, run `php8.4 artisan config:cache` and
`php8.4 artisan queue:restart`; already-created runs retain their saved proxy
options, so verify the expanded pool with a fresh run. No worker count is
automatically increased by this inventory change.

Focused verification after the inventory refresh passed 52 tests and 2,085
assertions across `ProxyPoolIntegrationTest`, `FairScanSchedulingTest`, and
`XhamsterAgeGateTest` on local PHP 8.2. All 60 endpoint/IP/country/ASN records were
also compared with the supplied inventory. The fallback test now explicitly
asserts the existing two-fallback policy rather than expecting four. These are
offline regression checks, not live connectivity or production load tests; the
full suite was not rerun for this inventory-only change.

## Local verification

```sh
php vendor/bin/phpunit --do-not-cache-result tests/Feature/FairScanSchedulingTest.php
```

The suite uses a real SQLite database queue and real Laravel job execution with
synthetic validators and no live service calls. It covers isolated modes, bounded
backlogs, short-run admission behind 1,364 modules, interleaving, terminal failures,
duplicate callbacks/delivery, transactional rollback, delayed targets, metrics,
the Artisan worker and rejection of cross-database queue configurations.
It is not a Linux/MySQL concurrency or production load benchmark.

### Verification recorded on 2026-10-22

Local environment: Windows, PHP 8.2.0, PHPUnit 11.5.55, SQLite in memory.
PHP syntax checks, scoped Pint checks and `git diff --check` passed.

- Queue/API/dashboard/skip/index regression suite: **51 tests, 1,751 assertions,
  all passing**, including 18 new scheduling tests and a fully drained synthetic
  1,364-module run. The small run completed after the first 15 bulk jobs, rather
  than waiting for the other 1,349 bulk jobs.
- Full suite: **814 tests, 16,198 assertions, 2 failures, no errors**. The queue
  tests passed. The unrelated failures were:
  - `PrivateEmailPivotValidatorTest`: Dropbox is expected to be a safety-blocked
    placeholder, but the existing active Dropbox validator returns a verdict.
    Its dedicated connector tests pass; the two expectations conflict.
  - `ProxyPoolIntegrationTest`: expects fallback ports 8020, 8019, 8007, 8006,
    whereas the committed configuration explicitly marks only 8020 and 8019 as
    fallbacks. Queue changes do not alter proxy tiers.
- The full test process needed `-d memory_limit=512M`; the local 128 MB CLI limit
  was insufficient for the whole suite (completed peak: 144 MB). This is a
  test-run override, not a change to server PHP memory settings.

Do not describe the full suite as green until those existing policy/test
inconsistencies are resolved. No production migration, configuration change,
worker restart or deployment was performed during local verification. No
production p95 improvement has yet been measured.

## Experimental async username pilot (not deployed)

The Discourse, Gitea and Lemmy pilot is **off by default**. It requires both
`SCANNER_USERNAME_ASYNC_PILOT=true` and the internal per-run option
`async_username_pilot=true`. Production run creation and pilot workers explicitly
reject activation. Normal runs, email jobs and every non-pilot validator retain
their existing execution path. No public API/UI option or Supervisor pool was
enabled by this change.

Eligible tasks use `scanner-username-pilot`; the same durable fair scheduling
window counts tasks across the ordinary and pilot queues. Existing skip flags,
target delays, proxy retries, normalization, profile enrichment, result inserts
and completion counters are reused. Each generated validator and each job's proxy
list is isolated per Fiber. A shared cURL multi handler runs API and enrichment
GETs concurrently. Proxy-capacity waits yield instead of freezing other probes.
This pilot deliberately retains the existing per-result database transactions;
it does not claim database batching gains.

`SCANNER_USERNAME_ASYNC_CONCURRENCY=40` is a **global pilot slot budget**. Several
small pilot processes may reserve slices of it, for example four processes at
`--concurrency=10`, not four at 40. Reservations use shared cache locks; do not
use the array/null cache in staging. Other legacy/email workers still share
the same per-proxy lease limit. A crashed process can retain pilot reservations
until their TTL expires. The cooperative job deadline cancels pending HTTP and
lease waits but cannot interrupt synchronous CPU/database stalls like the normal
worker's `pcntl` alarm. Hard-crash recovery and Linux/PHP8.4/MySQL load validation
remain requirements before any production rollout.

For an explicitly opted-in local/staging run, keep normal username workers
running for non-pilot modules and start the pilot worker separately:

```sh
php artisan scanner:work-username-pilot --concurrency=10 --max-jobs=0 --stop-when-empty
```

An opted-in run can be created through `QueuedScanService::startRun` with the
same target/category/module selection as before and option
`['async_username_pilot' => true]`. No module list is shortened. Without a
normal worker, non-pilot tasks will remain queued; without a pilot worker,
opted-in pilot tasks will remain queued. Do not opt in runs casually. To stop
the experiment, stop creating opted-in runs and drain **both** queues before
disabling the flag. Never purge jobs or staged tasks.

### Reproducible isolated benchmark

Requires PHP/ext-curl/PDO SQLite and Node.js. It creates a new database/output
directory under `storage/app`, binds the fixture server only to loopback, and
blocks stray external HTTP. It does not read production scan data, use proxy
credentials, or contact real module endpoints. Both arms execute original
`RunValidatorJob` jobs and the same scheduler, with identical fixture payloads,
delays, redirects, metadata follow-ups and global request concurrency. The
baseline uses ordinary Laravel workers; the pilot uses fewer cooperative workers.
Xdebug is disabled in child workers and worker startup is recorded separately.
Recycling is disabled during these trials to isolate the executor comparison.

```sh
php -d xdebug.mode=off scripts/benchmark_async_username.php --concurrency=4 --pilot-workers=2 --rounds=20 --delay-ms=10
```

The report checks identical module coverage, canonical result hashes (excluding
only latency/timestamps/row identities), request-trace hashes, concurrency peaks
and settled tasks on **every** trial. It reports throughput, p50/p95, startup and
summed worker peak PHP allocations (not aggregate RSS). For a valid comparison,
do not run test suites or other benchmark arms alongside timed trials. A short
smoke run can use `--rounds=1`. Raise concurrency only if the local machine has
enough free memory for the baseline's one-PHP-process-per-slot model.

Windows/SQLite and synthetic responses are not a production p95 forecast.
SQLite worker transactions reserve writes before reads to avoid deferred-upgrade
deadlocks; this is not a substitute for a MySQL staging benchmark. Fixture parity
proves that the executor preserves existing semantics, not that every remote
website is currently working or that existing parser behavior is infallible.

### Pilot results recorded on 2026-10-22 (local only)

Two clean runs reversed executor order, yielding 20 samples per arm at a
16-slot cap. Each sample checked all 1,124 eligible modules and made the same
1,468 fixture requests, including redirects and metadata follow-ups. Every
canonical result hash and request-trace hash matched. Recycling was disabled
and worker startup excluded from timed trials, equally in both arms.

| Metric | Current executor | Async pilot |
| --- | ---: | ---: |
| PHP workers | 16 | 4 (4 slots each) |
| Global request-slot cap | 16 | 16 |
| p50 | 16.62 s | 11.80 s |
| p95 (nearest rank) | 16.95 s | 12.18 s |
| Pooled checks/second | 67.47 | 94.63 |
| Checks/second per worker | 4.22 | 23.66 |
| Sum of worker peak PHP allocations | 704 MB | 176 MB |

This is 28.2% lower local p95, 40.2% greater total throughput, 5.61x throughput
per PHP worker and 75% lower summed PHP allocations. These allocations are not
aggregate RSS or total server memory. Observed fixture HTTP peaks were 5-7 for
the baseline and 7-11 for the pilot, below the same configured cap of 16.

The positive/negative fixture matrix covers all 1,124 bulk definitions. A mixed
end-to-end scan also compared all 1,487 registered username modules, preserving
every legacy module and stored result. The pilot suite passed **16 tests / 3,843
assertions**. The final full suite ran **854 tests / 20,542 assertions**: **853
passed**, with the previously recorded Dropbox expectation mismatch as the sole
failure (`Skipped` expected, existing connector returns `Not Registered`). No
email policy was changed to silence that unrelated failure. Pint, command
registration, and diff checks passed.

Evidence is under `storage/app/async-pilot-comparison-2026-10-22-34fab3/`
(`report.html` and `report.json`), with links to both raw 10-sample-per-arm
reports. The final JUnit report is `storage/app/async-pilot-final-full-suite.xml`.
Reproduce the balanced comparison sequentially with:

```sh
php -d xdebug.mode=off scripts/benchmark_async_username.php --concurrency=16 --pilot-workers=4 --rounds=10 --delay-ms=10 --order=pilot-first
php -d xdebug.mode=off scripts/benchmark_async_username.php --concurrency=16 --pilot-workers=4 --rounds=10 --delay-ms=10 --order=baseline-first
```

The 40-slot production profile, real proxy/site behavior and full production
1,361-module p95 were **not** benchmarked. The test host did not have enough
spare RAM for a safe 40-process reference arm. Linux/PHP8.4/MySQL staging checks
and hard-timeout/crash recovery validation are still required. The pilot remains
off by default, production activation is blocked, and nothing was deployed.
