<?php

use App\Services\OpsMetricsService;
use App\Services\Scanner\MetadataAuditService;
use App\Services\Scanner\MetadataBaselineValidationService;
use App\Services\Scanner\MetadataCapabilityService;
use App\Services\Scanner\MetadataRevalidationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('scanner:ops-snapshot', function (OpsMetricsService $metrics): int {
    $snapshot = $metrics->captureQueueSnapshot();

    $this->info(sprintf(
        'Captured queue snapshot: queued=%d reserved=%d active_runs=%d outstanding_results=%d',
        $snapshot['queued_jobs'],
        $snapshot['reserved_jobs'],
        $snapshot['active_runs'],
        $snapshot['outstanding_results'],
    ));

    return Command::SUCCESS;
})->purpose('Capture a DB-backed queue backlog snapshot for the ops dashboard');

Artisan::command(
    'scanner:metadata-readiness
    {--output= : Write the JSON readiness report to a file}
    {--json : Print the full JSON report to stdout}
    {--min-documented=250 : Minimum documented module count}
    {--min-level3=150 : Minimum documented modules at capability level 3+}
    {--min-level4=50 : Minimum documented modules at capability level 4}
    {--min-validated=0 : Minimum validated module count}
    {--min-validated-level3=0 : Minimum validated modules at capability level 3+}
    {--min-validated-level4=50 : Minimum validated modules at capability level 4}',
    function (MetadataCapabilityService $capability): int {
        $inventory = $capability->all();
        $summary = $capability->summary();
        $modeSummary = $capability->modeSummary();
        $emailFocus = $capability->validationGapSummary('email');

        $thresholds = [
            'min_documented' => (int) $this->option('min-documented'),
            'min_level3' => (int) $this->option('min-level3'),
            'min_level4' => (int) $this->option('min-level4'),
            'min_validated' => (int) $this->option('min-validated'),
            'min_validated_level3' => (int) $this->option('min-validated-level3'),
            'min_validated_level4' => (int) $this->option('min-validated-level4'),
        ];

        $report = [
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'mode_summary' => $modeSummary,
            'email_focus' => $emailFocus,
            'thresholds' => $thresholds,
        ];

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $outputPath = $this->option('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            $directory = dirname($outputPath);
            if ($directory !== '' && $directory !== '.') {
                File::ensureDirectoryExists($directory);
            }

            file_put_contents($outputPath, $json);
            $this->info('Wrote metadata readiness report to ' . $outputPath);
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Documented modules', (string) $summary['documented_modules']],
                ['Documented level 3+', (string) $summary['level_3_plus']],
                ['Documented level 4', (string) $summary['level_4']],
                ['Validated modules', (string) $summary['validated_modules']],
                ['Validated level 3+', (string) $summary['validated_level_3_plus']],
                ['Validated level 4', (string) $summary['validated_level_4']],
                ['Username documented', (string) $modeSummary['username']['documented_modules']],
                ['Email documented', (string) $modeSummary['email']['documented_modules']],
                ['Email promotion candidates', (string) $emailFocus['promotion_candidates']],
                ['Email candidates with baselines', (string) $emailFocus['promotion_candidates_with_baseline_targets']],
                ['Email candidates without baselines', (string) $emailFocus['promotion_candidates_without_baseline_targets']],
                ['Email safety-blocked', (string) $emailFocus['safety_blocked_modules']],
            ],
        );

        $failures = [];
        if ((int) $summary['documented_modules'] < $thresholds['min_documented']) {
            $failures[] = sprintf(
                'Documented modules (%d) fell below the required minimum of %d.',
                $summary['documented_modules'],
                $thresholds['min_documented']
            );
        }
        if ((int) $summary['level_3_plus'] < $thresholds['min_level3']) {
            $failures[] = sprintf(
                'Documented level 3+ modules (%d) fell below the required minimum of %d.',
                $summary['level_3_plus'],
                $thresholds['min_level3']
            );
        }
        if ((int) $summary['level_4'] < $thresholds['min_level4']) {
            $failures[] = sprintf(
                'Documented level 4 modules (%d) fell below the required minimum of %d.',
                $summary['level_4'],
                $thresholds['min_level4']
            );
        }
        if ((int) $summary['validated_modules'] < $thresholds['min_validated']) {
            $failures[] = sprintf(
                'Validated modules (%d) fell below the required minimum of %d.',
                $summary['validated_modules'],
                $thresholds['min_validated']
            );
        }
        if ((int) $summary['validated_level_3_plus'] < $thresholds['min_validated_level3']) {
            $failures[] = sprintf(
                'Validated level 3+ modules (%d) fell below the required minimum of %d.',
                $summary['validated_level_3_plus'],
                $thresholds['min_validated_level3']
            );
        }
        if ((int) $summary['validated_level_4'] < $thresholds['min_validated_level4']) {
            $failures[] = sprintf(
                'Validated level 4 modules (%d) fell below the required minimum of %d.',
                $summary['validated_level_4'],
                $thresholds['min_validated_level4']
            );
        }

        if ((bool) $this->option('json')) {
            $this->line($json);
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
)->purpose('Report metadata readiness counts and fail when configured acceptance thresholds are not met');

Artisan::command(
    'scanner:metadata-acceptance-audit
    {--output= : Write the JSON audit report to a file}
    {--json : Print the full JSON report to stdout}
    {--require-live-level3 : Fail unless the 150 Level 3+ requirement is met by live-validated capability instead of documented capability}
    {--revalidation-report= : Read a JSON report generated by scanner:metadata-revalidate}
    {--require-stable-overlays : Fail unless the supplied revalidation report proves overlay stability}
    {--max-unstable=0 : Maximum allowed unstable modules from the supplied revalidation report}
    {--max-degraded=0 : Maximum allowed degraded modules from the supplied revalidation report}
    {--max-blocked=0 : Maximum allowed blocked/rate-limited modules from the supplied revalidation report}
    {--max-inconclusive=0 : Maximum allowed inconclusive modules from the supplied revalidation report}
    {--max-broken=0 : Maximum allowed broken modules from the supplied revalidation report}',
    function (MetadataCapabilityService $capability): int {
        $summary = $capability->summary();
        $emailFocus = $capability->validationGapSummary('email');
        $failures = [];

        $requirements = [
            [
                'key' => 'documented_modules',
                'label' => 'Documented modules >= 250',
                'status' => $summary['documented_modules'] >= 250 ? 'proved' : 'failed',
                'evidence_type' => 'documented',
                'threshold' => 250,
                'observed' => $summary['documented_modules'],
                'notes' => 'Derived from active June-source module inventory excluding abandoned modules.',
            ],
            [
                'key' => 'documented_level_3_plus',
                'label' => 'Documented Level 3+ modules >= 150',
                'status' => $summary['level_3_plus'] >= 150 ? 'proved' : 'failed',
                'evidence_type' => 'documented',
                'threshold' => 150,
                'observed' => $summary['level_3_plus'],
                'notes' => 'Derived from June-source capability classification.',
            ],
            [
                'key' => 'validated_level_3_plus',
                'label' => 'Live-validated Level 3+ modules >= 150',
                'status' => $summary['validated_level_3_plus'] >= 150 ? 'proved' : 'unproved',
                'evidence_type' => 'validated',
                'threshold' => 150,
                'observed' => $summary['validated_level_3_plus'],
                'notes' => 'Derived from successful live proxy-backed validation overlays.',
            ],
            [
                'key' => 'documented_level_4',
                'label' => 'Documented Level 4 modules >= 50',
                'status' => $summary['level_4'] >= 50 ? 'proved' : 'failed',
                'evidence_type' => 'documented',
                'threshold' => 50,
                'observed' => $summary['level_4'],
                'notes' => 'Derived from June-source capability classification.',
            ],
            [
                'key' => 'validated_level_4',
                'label' => 'Live-validated Level 4 modules >= 50',
                'status' => $summary['validated_level_4'] >= 50 ? 'proved' : 'failed',
                'evidence_type' => 'validated',
                'threshold' => 50,
                'observed' => $summary['validated_level_4'],
                'notes' => 'Derived from successful live proxy-backed validation overlays.',
            ],
        ];

        $overallStatus = 'proved_with_documented_level3';
        $revalidationReport = null;
        $revalidationPath = $this->option('revalidation-report');
        if (is_string($revalidationPath) && trim($revalidationPath) !== '') {
            if (!is_file($revalidationPath)) {
                $failures[] = 'Revalidation report file was not found: ' . $revalidationPath;
                $overallStatus = 'failed';
            } else {
                $decoded = json_decode((string) file_get_contents($revalidationPath), true);
                if (!is_array($decoded) || !is_array($decoded['summary'] ?? null)) {
                    $failures[] = 'Revalidation report is not valid JSON in the expected scanner:metadata-revalidate format.';
                    $overallStatus = 'failed';
                } else {
                    $revalidationReport = $decoded;
                    $revalidationSummary = $decoded['summary'];
                    $maxUnstable = (int) $this->option('max-unstable');
                    $maxDegraded = (int) $this->option('max-degraded');
                    $maxBlocked = (int) $this->option('max-blocked');
                    $maxInconclusive = (int) $this->option('max-inconclusive');
                    $maxBroken = (int) $this->option('max-broken');

                    $requirements[] = [
                        'key' => 'validated_overlay_unstable_modules',
                        'label' => sprintf('Revalidated unstable overlay modules <= %d', $maxUnstable),
                        'status' => ((int) ($revalidationSummary['unstable_modules'] ?? 0)) <= $maxUnstable ? 'proved' : 'failed',
                        'evidence_type' => 'revalidated',
                        'threshold' => $maxUnstable,
                        'observed' => (int) ($revalidationSummary['unstable_modules'] ?? 0),
                        'notes' => 'Derived from scanner:metadata-revalidate drift checks against stored validated targets.',
                    ];
                    $requirements[] = [
                        'key' => 'validated_overlay_degraded_modules',
                        'label' => sprintf('Revalidated degraded overlay modules <= %d', $maxDegraded),
                        'status' => ((int) ($revalidationSummary['degraded_modules'] ?? 0)) <= $maxDegraded ? 'proved' : 'failed',
                        'evidence_type' => 'revalidated',
                        'threshold' => $maxDegraded,
                        'observed' => (int) ($revalidationSummary['degraded_modules'] ?? 0),
                        'notes' => 'Derived from scanner:metadata-revalidate modules whose observed level fell below the stored validated level.',
                    ];
                    $requirements[] = [
                        'key' => 'validated_overlay_blocked_modules',
                        'label' => sprintf('Revalidated blocked overlay modules <= %d', $maxBlocked),
                        'status' => ((int) ($revalidationSummary['blocked_modules'] ?? 0)) <= $maxBlocked ? 'proved' : 'failed',
                        'evidence_type' => 'revalidated',
                        'threshold' => $maxBlocked,
                        'observed' => (int) ($revalidationSummary['blocked_modules'] ?? 0),
                        'notes' => 'Derived from scanner:metadata-revalidate modules whose validated targets all failed with blocked, anti-bot, or rate-limited outcomes.',
                    ];
                    $requirements[] = [
                        'key' => 'validated_overlay_inconclusive_modules',
                        'label' => sprintf('Revalidated inconclusive overlay modules <= %d', $maxInconclusive),
                        'status' => ((int) ($revalidationSummary['inconclusive_modules'] ?? 0)) <= $maxInconclusive ? 'proved' : 'failed',
                        'evidence_type' => 'revalidated',
                        'threshold' => $maxInconclusive,
                        'observed' => (int) ($revalidationSummary['inconclusive_modules'] ?? 0),
                        'notes' => 'Derived from scanner:metadata-revalidate modules that could not be fairly evaluated because all validated targets failed with infrastructure-shaped network or TLS errors.',
                    ];
                    $requirements[] = [
                        'key' => 'validated_overlay_broken_modules',
                        'label' => sprintf('Revalidated broken overlay modules <= %d', $maxBroken),
                        'status' => ((int) ($revalidationSummary['broken_modules'] ?? 0)) <= $maxBroken ? 'proved' : 'failed',
                        'evidence_type' => 'revalidated',
                        'threshold' => $maxBroken,
                        'observed' => (int) ($revalidationSummary['broken_modules'] ?? 0),
                        'notes' => 'Derived from scanner:metadata-revalidate modules whose stored validated targets no longer produce any successful hits.',
                    ];
                }
            }
        } elseif ((bool) $this->option('require-stable-overlays')) {
            $failures[] = 'Overlay stability was required, but no --revalidation-report was supplied.';
            $overallStatus = 'failed';
        }

        foreach ($requirements as $requirement) {
            if ($requirement['status'] === 'failed') {
                $overallStatus = 'failed';
                $failures[] = sprintf(
                    '%s requirement failed: observed %d, expected at least %d.',
                    $requirement['label'],
                    $requirement['observed'],
                    $requirement['threshold']
                );
            }
        }

        $liveLevel3Requirement = collect($requirements)->firstWhere('key', 'validated_level_3_plus');
        if ($overallStatus !== 'failed' && is_array($liveLevel3Requirement) && $liveLevel3Requirement['status'] !== 'proved') {
            $overallStatus = 'proved_with_live_level3_gap';
            if ((bool) $this->option('require-live-level3')) {
                $failures[] = sprintf(
                    '%s requirement is not yet proved: observed %d, expected at least %d.',
                    $liveLevel3Requirement['label'],
                    $liveLevel3Requirement['observed'],
                    $liveLevel3Requirement['threshold']
                );
            }
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'overall_status' => $overallStatus,
            'strict_live_level3_required' => (bool) $this->option('require-live-level3'),
            'strict_overlay_stability_required' => (bool) $this->option('require-stable-overlays'),
            'summary' => $summary,
            'email_focus' => $emailFocus,
            'requirements' => $requirements,
            'revalidation' => $revalidationReport,
        ];

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $outputPath = $this->option('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            $directory = dirname($outputPath);
            if ($directory !== '' && $directory !== '.') {
                File::ensureDirectoryExists($directory);
            }

            file_put_contents($outputPath, $json);
            $this->info('Wrote metadata acceptance audit to ' . $outputPath);
        }

        $this->table(
            ['Requirement', 'Status', 'Observed', 'Threshold', 'Evidence'],
            array_map(
                static fn (array $requirement): array => [
                    (string) $requirement['label'],
                    (string) $requirement['status'],
                    (string) $requirement['observed'],
                    (string) $requirement['threshold'],
                    (string) $requirement['evidence_type'],
                ],
                $requirements,
            ),
        );

        if ((bool) $this->option('json')) {
            $this->line($json);
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
)->purpose('Report a requirement-by-requirement metadata acceptance audit for WebVetted readiness');

Artisan::command(
    'scanner:metadata-audit
    {mode : username or email}
    {targets* : One or more targets to audit}
    {--category= : Restrict the audit to a single category}
    {--module=* : Restrict the audit to one or more module keys}
    {--output= : Write the JSON report to a file}
    {--json : Print the full JSON report to stdout}
    {--only-found : Exclude non-hit results from the audit}
    {--allow-loud : Include loud email-style validators}
    {--no-nsfw : Exclude NSFW categories}
    {--use-proxy : Enable the configured proxy pool}
    {--no-proxy : Disable the configured proxy pool and force direct requests}
    {--proxy= : Override with a single proxy URL}
    {--stop=100 : Pattern expansion stop limit}
    {--delay=0 : Delay in seconds between expanded targets}
    {--enrich-metadata=1 : Enable profile HTML enrichment}
    {--min-level3=0 : Minimum number of audited results expected at observed metadata level 3+}
    {--min-level4=0 : Minimum number of audited results expected at observed metadata level 4}
    {--max-found-below-documented=999999 : Maximum allowed found results whose observed level is below documented capability}',
    function (MetadataAuditService $audit): int {
        $mode = (string) $this->argument('mode');
        if (!in_array($mode, ['username', 'email'], true)) {
            $this->error('Mode must be username or email.');

            return Command::FAILURE;
        }

        $moduleKeys = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $this->option('module')
        ), static fn (string $value): bool => $value !== ''));

        $report = $audit->audit(
            mode: $mode,
            targets: array_values((array) $this->argument('targets')),
            category: $this->option('category') ? (string) $this->option('category') : null,
            moduleKeys: $moduleKeys !== [] ? $moduleKeys : null,
            options: [
                'only_found' => (bool) $this->option('only-found'),
                'allow_loud' => (bool) $this->option('allow-loud'),
                'no_nsfw' => (bool) $this->option('no-nsfw'),
                'use_proxy' => (bool) $this->option('use-proxy'),
                'disable_proxy' => (bool) $this->option('no-proxy'),
                'proxy' => $this->option('proxy') ? (string) $this->option('proxy') : null,
                'stop' => (int) $this->option('stop'),
                'delay' => (float) $this->option('delay'),
                'enrich_metadata' => filter_var($this->option('enrich-metadata'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            ],
        );

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $outputPath = $this->option('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            $directory = dirname($outputPath);
            if ($directory !== '' && $directory !== '.') {
                File::ensureDirectoryExists($directory);
            }

            file_put_contents($outputPath, $json);
            $this->info('Wrote metadata audit report to ' . $outputPath);
        }

        $summary = $report['summary'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Audited results', (string) $summary['audited_results']],
                ['Found results', (string) $summary['found_results']],
                ['Not found results', (string) $summary['not_found_results']],
                ['Error results', (string) $summary['error_results']],
                ['Observed level 3+', (string) $summary['results_with_metadata_level_3_plus']],
                ['Observed level 4', (string) $summary['results_with_metadata_level_4']],
                ['Found below documented', (string) $summary['found_results_below_documented_level']],
            ],
        );

        $failures = [];
        $minLevel3 = (int) $this->option('min-level3');
        $minLevel4 = (int) $this->option('min-level4');
        $maxBelowDocumented = (int) $this->option('max-found-below-documented');

        if ((int) $summary['results_with_metadata_level_3_plus'] < $minLevel3) {
            $failures[] = sprintf(
                'Observed metadata level 3+ results (%d) fell below the required minimum of %d.',
                $summary['results_with_metadata_level_3_plus'],
                $minLevel3
            );
        }
        if ((int) $summary['results_with_metadata_level_4'] < $minLevel4) {
            $failures[] = sprintf(
                'Observed metadata level 4 results (%d) fell below the required minimum of %d.',
                $summary['results_with_metadata_level_4'],
                $minLevel4
            );
        }
        if ((int) $summary['found_results_below_documented_level'] > $maxBelowDocumented) {
            $failures[] = sprintf(
                'Found results below documented capability (%d) exceeded the allowed maximum of %d.',
                $summary['found_results_below_documented_level'],
                $maxBelowDocumented
            );
        }

        if ((bool) $this->option('json')) {
            $this->line($json);
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
)->purpose('Audit normalized metadata output for selected scanner modules and targets');

Artisan::command(
    'scanner:metadata-validate-baselines
    {mode : username or email}
    {--module=* : Restrict the validation batch to one or more module keys}
    {--output= : Write the JSON validation report to a file}
    {--export-overlay= : Write the proposed validation overlay PHP file}
    {--json : Print the full JSON report to stdout}
    {--allow-loud : Include loud email-style validators}
    {--no-nsfw : Exclude NSFW categories}
    {--use-proxy : Enable the configured proxy pool}
    {--no-proxy : Disable the configured proxy pool and force direct requests}
    {--proxy= : Override with a single proxy URL}
    {--stop=100 : Pattern expansion stop limit}
    {--delay=0 : Delay in seconds between expanded targets}
    {--enrich-metadata=1 : Enable profile HTML enrichment}',
    function (MetadataBaselineValidationService $validation): int {
        $mode = (string) $this->argument('mode');
        if (!in_array($mode, ['username', 'email'], true)) {
            $this->error('Mode must be username or email.');

            return Command::FAILURE;
        }

        $moduleKeys = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $this->option('module')
        ), static fn (string $value): bool => $value !== ''));

        $report = $validation->validate(
            mode: $mode,
            moduleKeys: $moduleKeys !== [] ? $moduleKeys : null,
            options: [
                'allow_loud' => (bool) $this->option('allow-loud'),
                'no_nsfw' => (bool) $this->option('no-nsfw'),
                'use_proxy' => (bool) $this->option('use-proxy'),
                'disable_proxy' => (bool) $this->option('no-proxy'),
                'proxy' => $this->option('proxy') ? (string) $this->option('proxy') : null,
                'stop' => (int) $this->option('stop'),
                'delay' => (float) $this->option('delay'),
                'enrich_metadata' => filter_var($this->option('enrich-metadata'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            ],
        );

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $outputPath = $this->option('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            $directory = dirname($outputPath);
            if ($directory !== '' && $directory !== '.') {
                File::ensureDirectoryExists($directory);
            }

            file_put_contents($outputPath, $json);
            $this->info('Wrote metadata baseline validation report to ' . $outputPath);
        }

        $overlayPath = $this->option('export-overlay');
        if (is_string($overlayPath) && trim($overlayPath) !== '') {
            $directory = dirname($overlayPath);
            if ($directory !== '' && $directory !== '.') {
                File::ensureDirectoryExists($directory);
            }

            $overlayPhp = "<?php\n\nreturn " . var_export($report['proposed_validation_overlay'], true) . ";\n";
            file_put_contents($overlayPath, $overlayPhp);
            $this->info('Wrote proposed validation overlay to ' . $overlayPath);
        }

        $summary = $report['summary'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Modules requested', (string) $summary['modules_requested']],
                ['Modules with proposed validation', (string) $summary['modules_with_proposed_validation']],
                ['Modules below documented', (string) $summary['modules_below_documented_level']],
                ['Stable modules', (string) ($summary['stable_modules'] ?? 0)],
                ['Partial modules', (string) ($summary['partial_modules'] ?? 0)],
                ['Blocked modules', (string) ($summary['blocked_modules'] ?? 0)],
                ['Inconclusive modules', (string) ($summary['inconclusive_modules'] ?? 0)],
                ['Broken modules', (string) ($summary['broken_modules'] ?? 0)],
                ['Successful targets', (string) $summary['successful_targets']],
                ['Failed targets', (string) $summary['failed_targets']],
            ],
        );

        $rows = array_map(
            static fn (array $module): array => [
                $module['module'],
                (string) ($module['documented_capability_level'] ?? ''),
                (string) ($module['current_validated_level'] ?? ''),
                (string) ($module['proposed_validated_level'] ?? ''),
                (string) ($module['validation_status'] ?? ''),
                implode(', ', $module['successful_targets'] ?? []),
            ],
            $report['modules']
        );
        if ($rows !== []) {
            $this->table(['Module', 'Documented', 'Current Validated', 'Proposed', 'Status', 'Successful Targets'], $rows);
        }

        if ((bool) $this->option('json')) {
            $this->line($json);
        }

        return Command::SUCCESS;
    }
)->purpose('Run a live validation batch for curated metadata baseline targets and propose overlay updates');

Artisan::command(
    'scanner:metadata-revalidate
    {mode : username or email}
    {--module=* : Restrict the revalidation batch to one or more module keys}
    {--output= : Write the JSON revalidation report to a file}
    {--json : Print the full JSON report to stdout}
    {--allow-loud : Include loud email-style validators}
    {--no-nsfw : Exclude NSFW categories}
    {--use-proxy : Enable the configured proxy pool}
    {--no-proxy : Disable the configured proxy pool and force direct requests}
    {--proxy= : Override with a single proxy URL}
    {--stop=100 : Pattern expansion stop limit}
    {--delay=0 : Delay in seconds between expanded targets}
    {--enrich-metadata=1 : Enable profile HTML enrichment}
    {--max-unstable=999999 : Maximum allowed modules with any failed or downgraded validated target}
    {--max-degraded=999999 : Maximum allowed modules whose observed level fell below the current validated level}
    {--max-blocked=999999 : Maximum allowed modules whose validated targets all failed with blocked, anti-bot, or rate-limited outcomes}
    {--max-inconclusive=999999 : Maximum allowed modules that could not be fairly evaluated due to infrastructure-shaped network or TLS failures}
    {--max-broken=999999 : Maximum allowed modules whose validated targets produced no successful hits}',
    function (MetadataRevalidationService $revalidation): int {
        $mode = (string) $this->argument('mode');
        if (!in_array($mode, ['username', 'email'], true)) {
            $this->error('Mode must be username or email.');

            return Command::FAILURE;
        }

        $moduleKeys = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $this->option('module')
        ), static fn (string $value): bool => $value !== ''));

        $report = $revalidation->revalidate(
            mode: $mode,
            moduleKeys: $moduleKeys !== [] ? $moduleKeys : null,
            options: [
                'allow_loud' => (bool) $this->option('allow-loud'),
                'no_nsfw' => (bool) $this->option('no-nsfw'),
                'use_proxy' => (bool) $this->option('use-proxy'),
                'disable_proxy' => (bool) $this->option('no-proxy'),
                'proxy' => $this->option('proxy') ? (string) $this->option('proxy') : null,
                'stop' => (int) $this->option('stop'),
                'delay' => (float) $this->option('delay'),
                'enrich_metadata' => filter_var($this->option('enrich-metadata'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            ],
        );

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $outputPath = $this->option('output');
        if (is_string($outputPath) && trim($outputPath) !== '') {
            $directory = dirname($outputPath);
            if ($directory !== '' && $directory !== '.') {
                File::ensureDirectoryExists($directory);
            }

            file_put_contents($outputPath, $json);
            $this->info('Wrote metadata revalidation report to ' . $outputPath);
        }

        $summary = $report['summary'];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Modules requested', (string) $summary['modules_requested']],
                ['Stable modules', (string) $summary['stable_modules']],
                ['Partial modules', (string) $summary['partial_modules']],
                ['Degraded modules', (string) $summary['degraded_modules']],
                ['Blocked modules', (string) $summary['blocked_modules']],
                ['Inconclusive modules', (string) $summary['inconclusive_modules']],
                ['Broken modules', (string) $summary['broken_modules']],
                ['Unstable modules', (string) $summary['unstable_modules']],
                ['Successful targets', (string) $summary['successful_targets']],
                ['Failed targets', (string) $summary['failed_targets']],
            ],
        );

        $failures = [];
        $maxUnstable = (int) $this->option('max-unstable');
        $maxDegraded = (int) $this->option('max-degraded');
        $maxBlocked = (int) $this->option('max-blocked');
        $maxInconclusive = (int) $this->option('max-inconclusive');
        $maxBroken = (int) $this->option('max-broken');

        if ((int) $summary['unstable_modules'] > $maxUnstable) {
            $failures[] = sprintf(
                'Unstable modules (%d) exceeded the allowed maximum of %d.',
                $summary['unstable_modules'],
                $maxUnstable
            );
        }
        if ((int) $summary['degraded_modules'] > $maxDegraded) {
            $failures[] = sprintf(
                'Degraded modules (%d) exceeded the allowed maximum of %d.',
                $summary['degraded_modules'],
                $maxDegraded
            );
        }
        if ((int) $summary['blocked_modules'] > $maxBlocked) {
            $failures[] = sprintf(
                'Blocked modules (%d) exceeded the allowed maximum of %d.',
                $summary['blocked_modules'],
                $maxBlocked
            );
        }
        if ((int) $summary['inconclusive_modules'] > $maxInconclusive) {
            $failures[] = sprintf(
                'Inconclusive modules (%d) exceeded the allowed maximum of %d.',
                $summary['inconclusive_modules'],
                $maxInconclusive
            );
        }
        if ((int) $summary['broken_modules'] > $maxBroken) {
            $failures[] = sprintf(
                'Broken modules (%d) exceeded the allowed maximum of %d.',
                $summary['broken_modules'],
                $maxBroken
            );
        }

        $rows = array_map(
            static fn (array $module): array => [
                $module['module'],
                (string) ($module['current_validated_level'] ?? ''),
                (string) ($module['revalidated_level'] ?? ''),
                (string) ($module['revalidation_status'] ?? ''),
                implode(', ', $module['failed_targets'] ?? []),
            ],
            $report['modules']
        );
        if ($rows !== []) {
            $this->table(['Module', 'Current Validated', 'Revalidated', 'Status', 'Failed Targets'], $rows);
        }

        if ((bool) $this->option('json')) {
            $this->line($json);
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error($failure);
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
)->purpose('Revalidate stored metadata validation overlays against their current validated targets');
