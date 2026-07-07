<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ops Metrics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Georgia', 'serif'],
                        body: ['Trebuchet MS', 'Verdana', 'sans-serif'],
                    },
                }
            }
        };
    </script>
</head>
<body class="min-h-screen bg-[linear-gradient(145deg,_#f8fafc,_#ecfeff_45%,_#fef3c7)] text-slate-900 font-body">
<style>
    .ops-chart-box {
        position: relative;
        height: 20rem;
    }

    .ops-chart-box--tall {
        position: relative;
        height: 26rem;
    }
</style>
@php
    $bootPayload = $dashboard;
    $selectedWindow = $dashboard['window'];
    $completion = $dashboard['completion'];
    $p95 = $dashboard['p95'];
    $reuse = $dashboard['reuse'];
    $validatorErrors = $dashboard['validator_errors'];
    $storage = $dashboard['storage'];
    $queue = $dashboard['queue'];
    $latestQueue = $queue['latest'] ?? [];
@endphp

<div class="max-w-7xl mx-auto px-4 py-10 space-y-8">
    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl overflow-hidden">
        <div class="grid lg:grid-cols-[1.15fr_0.85fr] gap-6 p-6 md:p-8">
            <div class="space-y-4">
                <p class="text-xs uppercase tracking-[0.3em] text-cyan-700 font-semibold">Ops Dashboard</p>
                <h1 class="font-display text-4xl md:text-5xl leading-tight">Scanner vitals from the database, not guesswork.</h1>
                <p class="text-slate-600 max-w-2xl">This surface starts with the six highest-signal app vitals: completion, latency, reuse, validator errors, storage growth, and queue backlog.</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    <span class="rounded-full bg-slate-900 text-white px-3 py-1">{{ $dashboard['window_label'] }}</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">DB-backed only</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">{{ $dashboard['bucket'] === 'hour' ? 'Hourly buckets' : 'Daily buckets' }}</span>
                </div>
            </div>
            <div class="rounded-2xl bg-slate-900 text-slate-100 p-5 space-y-3">
                <h2 class="text-lg font-semibold">What’s tracked</h2>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li>Completion rate from settled scan runs.</li>
                    <li>P95 time to final result from completed runs.</li>
                    <li>Public API reuse and cache hit rate from stored request events.</li>
                    <li>Validator error rate by module from persisted results.</li>
                    <li>Storage growth from scan run tables.</li>
                    <li>Queue backlog from recurring DB snapshots.</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-5">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-slate-600">Timeframe</span>
            @foreach($windowPresets as $windowKey => $preset)
                <a
                    href="{{ route('ops.metrics', ['window' => $windowKey]) }}"
                    class="inline-flex items-center rounded-full px-4 py-2 text-sm transition
                        {{ $selectedWindow === $windowKey ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-700 border border-slate-200 hover:border-slate-300 hover:bg-slate-50' }}"
                >
                    {{ $preset['label'] }}
                </a>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-slate-500">Short windows switch charts to hourly buckets. Longer windows stay daily.</p>
    </section>

    <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="rounded-3xl bg-white/90 p-5 shadow">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Completion Rate</p>
            <p class="mt-2 text-4xl font-semibold text-emerald-700">{{ $completion['rate'] !== null ? number_format($completion['rate'], 1) . '%' : 'n/a' }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ number_format($completion['completed']) }} completed of {{ number_format($completion['settled']) }} settled runs</p>
        </div>
        <div class="rounded-3xl bg-white/90 p-5 shadow">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">P95 Final Result</p>
            <p class="mt-2 text-4xl font-semibold text-cyan-700">{{ $p95['display'] }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ number_format($p95['samples']) }} completed runs in sample</p>
        </div>
        <div class="rounded-3xl bg-white/90 p-5 shadow">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Cache / Reuse Hit Rate</p>
            <p class="mt-2 text-4xl font-semibold text-violet-700">{{ $reuse['rate'] !== null ? number_format($reuse['rate'], 1) . '%' : 'n/a' }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ number_format($reuse['reused']) }} reused, {{ number_format($reuse['cached']) }} cached of {{ number_format($reuse['total']) }} API requests</p>
        </div>
        <div class="rounded-3xl bg-white/90 p-5 shadow">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Validator Error Rate</p>
            <p class="mt-2 text-4xl font-semibold text-amber-600">{{ $validatorErrors['overall_rate'] !== null ? number_format($validatorErrors['overall_rate'], 1) . '%' : 'n/a' }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ number_format($validatorErrors['total_errors']) }} errors out of {{ number_format($validatorErrors['total_results']) }} stored results</p>
        </div>
        <div class="rounded-3xl bg-white/90 p-5 shadow">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Storage Footprint</p>
            <p class="mt-2 text-4xl font-semibold text-slate-900">{{ number_format($storage['total_results']) }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ number_format($storage['total_runs']) }} scan runs and {{ number_format($storage['window_results']) }} new results in the selected window</p>
        </div>
        <div class="rounded-3xl bg-white/90 p-5 shadow">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Queue Backlog</p>
            <p class="mt-2 text-4xl font-semibold text-rose-700">{{ number_format($latestQueue['outstanding_results'] ?? 0) }}</p>
            <p class="mt-2 text-sm text-slate-600">
                {{ number_format($latestQueue['queued_jobs'] ?? 0) }} waiting jobs, {{ number_format($latestQueue['reserved_jobs'] ?? 0) }} reserved
                @if(!empty($latestQueue['captured_label']))
                    · snapshot {{ $latestQueue['captured_label'] }}
                @endif
            </p>
        </div>
    </section>

    <section class="grid xl:grid-cols-2 gap-6">
        <div class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-4">
            <div>
                <h2 class="text-2xl font-display">Completion Rate</h2>
                <p class="text-sm text-slate-600 mt-1">Daily completed vs failed runs, with completion rate overlaid.</p>
            </div>
            <div class="ops-chart-box">
                <canvas id="completion-chart"></canvas>
            </div>
        </div>
        <div class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-4">
            <div>
                <h2 class="text-2xl font-display">P95 Time To Final Result</h2>
                <p class="text-sm text-slate-600 mt-1">Daily p95 latency derived from completed runs only.</p>
            </div>
            <div class="ops-chart-box">
                <canvas id="p95-chart"></canvas>
            </div>
        </div>
    </section>

    <section class="grid xl:grid-cols-2 gap-6">
        <div class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-4">
            <div>
                <h2 class="text-2xl font-display">Cache / Reuse Hit Rate</h2>
                <p class="text-sm text-slate-600 mt-1">Public API create requests, including reused and fully cached returns.</p>
            </div>
            <div class="ops-chart-box">
                <canvas id="reuse-chart"></canvas>
            </div>
        </div>
        <div class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-4">
            <div>
                <h2 class="text-2xl font-display">Storage Growth</h2>
                <p class="text-sm text-slate-600 mt-1">Daily row growth and cumulative totals for scan runs and scan results.</p>
            </div>
            <div class="ops-chart-box">
                <canvas id="storage-chart"></canvas>
            </div>
        </div>
    </section>

    <section class="grid xl:grid-cols-[1fr_0.9fr] gap-6 items-start">
        <div class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-4">
            <div>
                <h2 class="text-2xl font-display">Validator Error Rate By Module</h2>
                <p class="text-sm text-slate-600 mt-1">Top modules by stored error rate in the current window.</p>
            </div>
            <div class="ops-chart-box--tall">
                <canvas id="validator-errors-chart"></canvas>
            </div>
        </div>
        <div class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/80">
                <h2 class="text-2xl font-display">Worst Modules</h2>
                <p class="text-sm text-slate-600 mt-1">Highest error rate first, then absolute error volume.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-700">
                    <tr>
                        <th class="text-left px-4 py-3">Mode</th>
                        <th class="text-left px-4 py-3">Module</th>
                        <th class="text-left px-4 py-3">Errors</th>
                        <th class="text-left px-4 py-3">Total</th>
                        <th class="text-left px-4 py-3">Rate</th>
                        <th class="text-left px-4 py-3">Skip Controls</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($validatorErrors['modules'] as $module)
                        <tr>
                            <td class="px-4 py-3 text-xs uppercase tracking-[0.2em] text-slate-500">{{ $module['mode'] }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $module['label'] }}</div>
                                <div class="text-xs font-mono text-slate-500">{{ $module['key'] }}</div>
                                @if(!empty($module['skip_active']))
                                    <div class="mt-2 inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                                        {{ $module['skip_label'] ?? 'Skipped' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-amber-700">{{ number_format($module['errors']) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ number_format($module['total']) }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ number_format($module['rate'], 1) }}%</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('ops.module-skips.update') }}">
                                        @csrf
                                        <input type="hidden" name="mode" value="{{ $module['mode'] }}">
                                        <input type="hidden" name="module_key" value="{{ $module['key'] }}">
                                        <input type="hidden" name="action" value="set">
                                        <input type="hidden" name="duration" value="permanent">
                                        <input type="hidden" name="window" value="{{ $selectedWindow }}">
                                        <button type="submit" class="rounded-full bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800">Permanent</button>
                                    </form>
                                    <form method="POST" action="{{ route('ops.module-skips.update') }}">
                                        @csrf
                                        <input type="hidden" name="mode" value="{{ $module['mode'] }}">
                                        <input type="hidden" name="module_key" value="{{ $module['key'] }}">
                                        <input type="hidden" name="action" value="set">
                                        <input type="hidden" name="duration" value="6h">
                                        <input type="hidden" name="window" value="{{ $selectedWindow }}">
                                        <button type="submit" class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 hover:border-amber-400">6hr</button>
                                    </form>
                                    @if(!empty($module['skip_active']))
                                        <form method="POST" action="{{ route('ops.module-skips.update') }}">
                                            @csrf
                                            <input type="hidden" name="mode" value="{{ $module['mode'] }}">
                                            <input type="hidden" name="module_key" value="{{ $module['key'] }}">
                                            <input type="hidden" name="action" value="clear">
                                            <input type="hidden" name="window" value="{{ $selectedWindow }}">
                                            <button type="submit" class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-800 hover:border-emerald-400">Clear</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">No validator result rows yet for this window.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-4">
        <div>
            <h2 class="text-2xl font-display">Queue Backlog</h2>
            <p class="text-sm text-slate-600 mt-1">Historical backlog from recurring queue snapshots stored in the database.</p>
        </div>
        <div class="ops-chart-box">
            <canvas id="queue-chart"></canvas>
        </div>
    </section>
</div>

<script>
(() => {
    const boot = {!! json_encode($bootPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    const colors = {
        emerald: '#059669',
        cyan: '#0891b2',
        violet: '#7c3aed',
        amber: '#d97706',
        rose: '#e11d48',
        slate: '#334155',
        sky: '#0284c7',
        orange: '#ea580c',
        grid: 'rgba(148, 163, 184, 0.22)',
    };

    function baseOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                    },
                },
            },
            scales: {
                x: {
                    grid: {
                        color: colors.grid,
                    },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: colors.grid,
                    },
                },
            },
        };
    }

    new Chart(document.getElementById('completion-chart'), {
        type: 'bar',
        data: {
            labels: boot.completion.chart.labels,
            datasets: [
                {
                    label: 'Completed',
                    data: boot.completion.chart.completed,
                    backgroundColor: 'rgba(5, 150, 105, 0.78)',
                    borderRadius: 8,
                },
                {
                    label: 'Failed',
                    data: boot.completion.chart.failed,
                    backgroundColor: 'rgba(225, 29, 72, 0.72)',
                    borderRadius: 8,
                },
                {
                    type: 'line',
                    label: 'Completion rate %',
                    data: boot.completion.chart.rate,
                    borderColor: colors.slate,
                    backgroundColor: colors.slate,
                    tension: 0.35,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            ...baseOptions(),
            scales: {
                ...baseOptions().scales,
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        drawOnChartArea: false,
                    },
                },
            },
        },
    });

    new Chart(document.getElementById('p95-chart'), {
        type: 'line',
        data: {
            labels: boot.p95.chart.labels,
            datasets: [
                {
                    label: 'P95 seconds',
                    data: boot.p95.chart.seconds,
                    borderColor: colors.cyan,
                    backgroundColor: 'rgba(8, 145, 178, 0.18)',
                    fill: true,
                    tension: 0.35,
                },
            ],
        },
        options: baseOptions(),
    });

    new Chart(document.getElementById('reuse-chart'), {
        type: 'bar',
        data: {
            labels: boot.reuse.chart.labels,
            datasets: [
                {
                    label: 'API requests',
                    data: boot.reuse.chart.total,
                    backgroundColor: 'rgba(51, 65, 85, 0.80)',
                    borderRadius: 8,
                },
                {
                    label: 'Reused',
                    data: boot.reuse.chart.reused,
                    backgroundColor: 'rgba(124, 58, 237, 0.75)',
                    borderRadius: 8,
                },
                {
                    label: 'Cached',
                    data: boot.reuse.chart.cached,
                    backgroundColor: 'rgba(14, 165, 233, 0.75)',
                    borderRadius: 8,
                },
            ],
        },
        options: baseOptions(),
    });

    new Chart(document.getElementById('storage-chart'), {
        type: 'bar',
        data: {
            labels: boot.storage.chart.labels,
            datasets: [
                {
                    label: 'New runs',
                    data: boot.storage.chart.runs_daily,
                    backgroundColor: 'rgba(234, 88, 12, 0.78)',
                    borderRadius: 8,
                    yAxisID: 'y',
                },
                {
                    label: 'New results',
                    data: boot.storage.chart.results_daily,
                    backgroundColor: 'rgba(2, 132, 199, 0.76)',
                    borderRadius: 8,
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: 'Cumulative runs',
                    data: boot.storage.chart.runs_cumulative,
                    borderColor: colors.orange,
                    backgroundColor: colors.orange,
                    tension: 0.35,
                    yAxisID: 'y1',
                },
                {
                    type: 'line',
                    label: 'Cumulative results',
                    data: boot.storage.chart.results_cumulative,
                    borderColor: colors.sky,
                    backgroundColor: colors.sky,
                    tension: 0.35,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            ...baseOptions(),
            scales: {
                ...baseOptions().scales,
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                },
            },
        },
    });

    new Chart(document.getElementById('validator-errors-chart'), {
        type: 'bar',
        data: {
            labels: boot.validator_errors.chart.labels,
            datasets: [
                {
                    label: 'Error rate %',
                    data: boot.validator_errors.chart.rates,
                    backgroundColor: 'rgba(217, 119, 6, 0.78)',
                    borderRadius: 8,
                },
            ],
        },
        options: {
            ...baseOptions(),
            indexAxis: 'y',
        },
    });

    new Chart(document.getElementById('queue-chart'), {
        type: 'line',
        data: {
            labels: boot.queue.chart.labels,
            datasets: [
                {
                    label: 'Queued jobs',
                    data: boot.queue.chart.queued_jobs,
                    borderColor: colors.rose,
                    backgroundColor: 'rgba(225, 29, 72, 0.12)',
                    tension: 0.35,
                },
                {
                    label: 'Reserved jobs',
                    data: boot.queue.chart.reserved_jobs,
                    borderColor: colors.violet,
                    backgroundColor: 'rgba(124, 58, 237, 0.12)',
                    tension: 0.35,
                },
                {
                    label: 'Active runs',
                    data: boot.queue.chart.active_runs,
                    borderColor: colors.slate,
                    backgroundColor: 'rgba(51, 65, 85, 0.10)',
                    tension: 0.35,
                },
                {
                    label: 'Outstanding results',
                    data: boot.queue.chart.outstanding_results,
                    borderColor: colors.cyan,
                    backgroundColor: 'rgba(8, 145, 178, 0.12)',
                    tension: 0.35,
                },
            ],
        },
        options: baseOptions(),
    });
})();
</script>
</body>
</html>
