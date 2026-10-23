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

## Proxy lease cleanup and database-cache contention fix (2026-10-23)

The proxy manager no longer holds one pool-wide database-cache mutex. It claims
owner-tagged atomic locks per endpoint/slot, reads cooldowns in one batch and
updates health under a short, non-blocking per-endpoint lock. The HTTP request is
not serialized with other endpoints. Existing proxy tiers, retry rules, module
classification and result logic are unchanged.

Site-specific 403/429/challenge responses still get the configured per-module
retry, but no longer quarantine a transport endpoint for unrelated sites. Actual
proxy authentication/resolution failures still affect endpoint health. Existing
cooldowns remain honored until their configured expiry; do not flush cache to
erase them. The database queue connector also retries deadlocked reservation
transactions up to five times before a handler starts. If those retries are
exhausted, the unexecuted job remains available rather than becoming a terminal
scanner Error. This does not increase HTTP retries or executed-job attempts.

Cleanup is in `finally`, including thrown requests and failed health updates. A
transient release error gets one retry; an unavailable cache is logged and the
lease expires. Release checks its owner and cannot delete a replacement owner's
lease. Default `SCANNER_PROXY_LEASE_SECONDS=90`; the effective lifetime is at
least the configured scanner job timeout plus 30 seconds. All workers using the
same proxy pool must use the same shared cache/prefix and concurrency settings.
This does not coordinate with separate localhost or other-server caches.

### Deployment and rollback boundary

**Do not mix old counter-based workers and the new v2 lease workers.** A rolling
`queue:restart` alone is not sufficient. No new migration or Redis service is
required; Laravel's existing `cache_locks` table is used with database cache.

1. Pause new scan creation upstream, including synchronous requests. Keep old
   workers running to drain. Do not put Laravel in maintenance mode while relying
   on normal workers to drain: maintenance mode can pause them.
2. Verify these counts are zero, repeatedly, while ingress remains paused:

   ```sql
   SELECT COUNT(*) AS active_runs FROM scan_runs WHERE status IN ('queued', 'running');
   SELECT COUNT(*) AS scanner_jobs FROM jobs
     WHERE queue IN ('scanner', 'scanner-username', 'scanner-email');
   SELECT COUNT(*) AS scanner_tasks FROM scan_run_tasks;
   ```

3. Stop every scanner worker group, including shared/legacy groups, using the
   actual Supervisor group names. Confirm they are stopped, then install the
   patch, including `ScannerDiagnostics.php` and both new `app/Queue/` classes.
   Refresh Composer's optimized autoload files if used. Reload long-lived HTTP
   workers/PHP-FPM and its OPcache as appropriate too, so synchronous requests
   cannot retain v1 proxy code. Keep ingress paused.
4. Keep `CACHE_STORE=database`, existing worker counts and existing proxy rules.
   The new lease setting is optional because 90 seconds is the default. Rebuild
   the Laravel configuration cache and start only the patched workers. Do not
   clear the whole application cache or purge queues. Old v1 active-counter keys
   are not used by v2 and may expire naturally after all old workers have stopped.
5. Test one run of each mode, then a bounded mixed-mode burst. Inspect result
   coverage, structured diagnostics and final queue/lease drain, not just the
   `completed` status. Resume ingress gradually. A 55-worker localhost test is
   not a capacity certification for a 4 GB / 2 vCPU production host.

Rollback also requires pausing ingress, draining and stopping **all** patched
workers before starting any old workers. Do not switch cache drivers/prefixes or
force-delete lease rows while scans are active. Recover a stuck run explicitly
rather than marking it complete or purging its remaining checks.

### Diagnostics and repeatable tests

Logs use the event `Scanner diagnostic`, with phase, run ID, mode, module,
target HMAC, endpoint fingerprint and exception class where available. They do
not attach exception objects, request options, raw targets, full URLs or proxy
credentials. Empty exception messages get a class/phase fallback. Release and
health failures do not overwrite successful account results. Result errors are
also logged, so they are visible even when Laravel considers the job successful.

```bash
php -d xdebug.mode=off vendor/phpunit/phpunit/phpunit --do-not-cache-result \
  tests/Feature/ProxyLeaseSafetyTest.php tests/Feature/ProxyPoolIntegrationTest.php
```

The live overlap harness is `scripts/test_proxy_overlap_live.php`. It requires
the local Windows MariaDB runtime, runs a dedicated loopback-only database and
55 regular synchronous workers, and sends real approved targets to service
endpoints through the configured 60 proxies. It excludes all notifying email
modules and redacts shareable reports. Its private database/log artifacts are
not deployment files and must not be committed or published. Stop it gracefully
and verify its worker/database helpers exit before another experiment. Worker
counts can be lowered with `--username-workers=20 --email-workers=5`; changing
burst size with `--usernames=1` changes the workload too, so timings from those
profiles are not a worker-scaling A/B comparison. Its existing direct exceptions
are Minecraft/Mix (username) and Adobe/Girlslife (email). They are explicitly
recorded and are not silently rerouted through proxies by the test.

### Verification recorded on 2026-10-23

- Focused proxy/queue/fairness/dashboard/index gate: **53 tests, 1,711
  assertions, all passing**. Covers thrown requests, health/release outages,
  owner fencing, expiry, safe logs and reservation deadlock recovery.
- Active application suite (deleted async suites excluded): **846 tests,
  16,247 assertions, 13 failures**. All 13 reproduce with unmodified HEAD runtime
  classes (`2c02ddd04928ed806be5fd586c4e0dd649d31b66`) in a separate 182-case
  baseline check. They concern existing platform fixtures/URL expectations:
  DeviantArt, Dropbox, Academia, Daily.dev, SourceForge, 500px, Reddit,
  Stack Overflow, Instagram and Threads. They were not hidden or changed to
  make this infrastructure patch pass; the whole suite is not green.
- Two 55-worker stress bursts: **18,954 expected results**, no duplicates,
  blank errors, proxy-capacity errors or SQL-lock result errors, and fully
  drained queues/tasks/leases. However, one retry-limit error and many upstream
  failures occurred; username scans returned only 8–14 hits. The early harness
  also generated 12 synthetic Adobe/Girlslife errors by blocking their existing
  direct requests. Those original results remain preserved and this profile
  is **not** an all-modules accuracy/capacity pass.
- Two corrected 25-worker controls (20 username-priority + 5 email workers),
  each overlapping one full username scan and three silent-email scans:
  **4,084 expected results**, zero blank/capacity/lock/retry-limit errors, zero
  failed jobs or leftover leases, and 14–15 username hits. No positive/negative
  verdict contradictions or stable identity changes were observed. Pastebin
  timed out once; two successful rows had partial supplementary metadata.
- Worker private-memory peaks were approximately **2,979–2,995 MiB at 55**
  versus **1,328 MiB at 25**, excluding MariaDB/coordinator. These profiles use
  different burst sizes and do not establish worker scaling or production p95.
- All 60 proxies passed preflight and were exercised in every completed burst.
  The local registry contained 1,487 username and 185 silent-email modules;
  41 notifying email modules were excluded, and configured auto-skips retained.
  Production ops skip flags were not imported. Upstream module errors remain;
  integrity/ownership/redaction checks are not a claim that every service works.

Shareable live reports are under `storage/app/proxy-overlap-20261023-*`, separate
from private database/server artifacts. All temporary workers, samplers and
database instances exited. No production deployment, restart or data mutation
was performed. Do not treat the 55-worker stress profile as suitable sizing for
the 4 GB / 2 vCPU host.
