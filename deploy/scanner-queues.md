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
