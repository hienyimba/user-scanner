<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Scanner</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="min-h-screen bg-[linear-gradient(135deg,_#f8fafc,_#f7ede2_55%,_#e2e8f0)] text-slate-900 font-body">
<div class="max-w-7xl mx-auto py-10 px-4 space-y-8">
    <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl overflow-hidden">
        <div class="grid lg:grid-cols-[1.2fr_0.8fr] gap-6 p-6 md:p-8">
            <div class="space-y-4">
                <p class="text-xs uppercase tracking-[0.3em] text-teal-700 font-semibold">Laravel Scanner</p>
                <h1 class="font-display text-4xl md:text-5xl leading-tight">Pure PHP conversion with Python-style scan behavior.</h1>
                <p class="text-slate-600 max-w-2xl">Single scans, queued category runs, proxy controls, pattern expansion, module filters, exports, and grouped results in one Laravel-first workflow.</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    <span class="rounded-full bg-slate-900 text-white px-3 py-1">Username + Email</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Validator-level progress</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Queue-backed category scans</span>
                </div>
            </div>
            <div class="rounded-2xl bg-slate-900 text-slate-100 p-5 space-y-3">
                <h2 class="text-lg font-semibold">Behavior Notes</h2>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li>Category scans now queue one validator job per target/validator pair.</li>
                    <li>The web page returns immediately and updates as results arrive.</li>
                    <li>Email loud modules are still skipped by default unless you enable them.</li>
                    <li>Small explicit scans can still complete inline when the matrix is tiny.</li>
                </ul>
            </div>
        </div>
    </section>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="font-semibold text-rose-700 mb-1">Please fix the following:</p>
            <ul class="list-disc ml-5 text-sm text-rose-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="grid xl:grid-cols-[1.3fr_0.7fr] gap-6 items-start">
        <form method="POST" action="{{ route('scanner.run') }}" class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 md:p-8 space-y-6">
            @csrf
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-display">Scan</h2>
                    <p class="text-sm text-slate-600 mt-1">Category runs will queue immediately. Small explicit scans can still render in the same request.</p>
                </div>
                <div class="text-xs text-slate-500">Pattern stop limit defaults to <span class="font-mono">100</span>.</div>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mode</label>
                    <select name="mode" id="single-mode" class="w-full rounded-xl border-slate-300" required>
                        <option value="username" @selected(($input['mode'] ?? 'username') === 'username')>Username</option>
                        <option value="email" @selected(($input['mode'] ?? '') === 'email')>Email</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                    <select name="category" id="single-category" class="w-full rounded-xl border-slate-300">
                        <option value="">All categories</option>
                        @foreach(($categoryCatalog ?? []) as $category)
                            <option value="{{ $category }}" @selected(($input['category'] ?? '') === $category)>{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Target</label>
                    <input type="text" name="target" value="{{ $input['target'] ?? '' }}" class="w-full rounded-xl border-slate-300" required placeholder="johndoe, john@example.com, john[0-9]{1-2}">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Modules</label>
                <select name="module_keys[]" id="single-module-keys" multiple size="8" class="w-full rounded-2xl border-slate-300">
                    @foreach(($moduleCatalog ?? []) as $mod)
                        <option value="{{ $mod['key'] }}" @selected(in_array($mod['key'], $input['module_keys'] ?? [], true))>
                            {{ ucfirst($mod['category']) }} / {{ $mod['site_name'] }}{{ !empty($mod['loud']) ? ' [loud]' : '' }}{{ !empty($mod['nsfw']) ? ' [nsfw]' : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-slate-500">Leave empty to scan every module in the selected mode/category.</p>
            </div>

            <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="only_found" value="1" @checked($input['only_found'] ?? false)>
                    <span class="text-sm">Only show found hits</span>
                </label>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="allow_loud" value="1" @checked($input['allow_loud'] ?? false)>
                    <span class="text-sm">Allow loud modules</span>
                </label>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="no_nsfw" value="1" @checked($input['no_nsfw'] ?? false)>
                    <span class="text-sm">Exclude NSFW</span>
                </label>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="verbose" value="1" @checked($input['verbose'] ?? false)>
                    <span class="text-sm">Verbose URLs</span>
                </label>
            </div>

            <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Permutation stop</label>
                    <input type="number" min="1" max="1000" name="stop" value="{{ $input['stop'] ?? 100 }}" class="w-full rounded-xl border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Delay (seconds)</label>
                    <input type="number" min="0" max="10" step="0.1" name="delay" value="{{ $input['delay'] ?? 0 }}" class="w-full rounded-xl border-slate-300">
                </div>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 mt-6 md:mt-0">
                    <input type="checkbox" name="use_proxy" value="1" @checked($input['use_proxy'] ?? false)>
                    <span class="text-sm">Use proxy rotation</span>
                </label>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 mt-6 md:mt-0">
                    <input type="checkbox" name="validate_proxies" value="1" @checked($input['validate_proxies'] ?? false)>
                    <span class="text-sm">Validate proxies first</span>
                </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Proxy list</label>
                        <textarea name="proxy_list" rows="4" class="w-full rounded-2xl border-slate-300" placeholder="http://127.0.0.1:8080&#10;socks5://127.0.0.1:9050">{{ $input['proxy_list'] ?? '' }}</textarea>
            </div>

            <button type="submit" class="inline-flex items-center px-5 py-3 rounded-full bg-slate-900 text-white hover:bg-slate-800">Run Scan</button>
        </form>

        <aside class="space-y-6">
            <div class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6">
                <h3 class="text-xl font-display">Batch Runs</h3>
                <p class="text-sm text-slate-600 mt-1">Queue one target per line, then watch persisted progress update below.</p>

                <div class="space-y-3 mt-4">
                    <select id="batch-mode" class="w-full rounded-xl border-slate-300">
                        <option value="username">Username</option>
                        <option value="email">Email</option>
                    </select>
                    <select id="batch-category" class="w-full rounded-xl border-slate-300">
                        <option value="">All categories</option>
                        @foreach(($categoryCatalog ?? []) as $category)
                            <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                    <select id="batch-module-keys" multiple size="6" class="w-full rounded-2xl border-slate-300">
                        @foreach(($moduleCatalog ?? []) as $mod)
                            <option value="{{ $mod['key'] }}">{{ ucfirst($mod['category']) }} / {{ $mod['site_name'] }}</option>
                        @endforeach
                    </select>
                    <textarea id="batch-targets" rows="5" class="w-full rounded-2xl border-slate-300" placeholder="alice&#10;bob&#10;charlie@example.com"></textarea>
                    <input id="batch-target-file" type="file" class="w-full text-sm text-slate-600" accept=".txt,.csv">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <label class="flex items-center gap-2"><input id="batch-only-found" type="checkbox">Only found</label>
                        <label class="flex items-center gap-2"><input id="batch-allow-loud" type="checkbox">Allow loud</label>
                        <label class="flex items-center gap-2"><input id="batch-no-nsfw" type="checkbox">Exclude NSFW</label>
                        <label class="flex items-center gap-2"><input id="batch-validate-proxies" type="checkbox">Validate proxies</label>
                    </div>
                    <textarea id="batch-proxy-list" rows="3" class="w-full rounded-2xl border-slate-300" placeholder="Optional proxy list">{{ $input['proxy_list'] ?? '' }}</textarea>
                    <div class="grid grid-cols-2 gap-3">
                        <input id="batch-stop" type="number" min="1" max="1000" value="100" class="rounded-xl border-slate-300" placeholder="Stop">
                        <input id="batch-delay" type="number" min="0" max="10" step="0.1" value="0" class="rounded-xl border-slate-300" placeholder="Delay">
                    </div>
                    <div class="flex gap-2">
                        <button id="start-batch" type="button" class="px-4 py-2 rounded-full bg-emerald-700 text-white hover:bg-emerald-800">Start Batch</button>
                        <button id="refresh-run" type="button" class="px-4 py-2 rounded-full bg-slate-700 text-white hover:bg-slate-800">Refresh Latest</button>
                    </div>
                </div>
            </div>

            <div id="run-status" class="{{ !empty($currentRun) ? '' : 'hidden' }} rounded-3xl bg-slate-900 text-slate-100 p-6 shadow-xl">
                <div class="text-sm text-slate-300 mb-2"><span id="run-id"></span> · <span id="run-meta"></span></div>
                <div class="w-full bg-slate-700 rounded h-3 overflow-hidden">
                    <div id="run-progress" class="h-3 bg-emerald-500" style="width:0%"></div>
                </div>
                <div class="mt-2 text-xs text-slate-300" id="run-progress-text"></div>
                <div class="mt-3 flex gap-3 text-xs">
                    <a id="export-json" class="underline" href="#" target="_blank">Export JSON</a>
                    <a id="export-csv" class="underline" href="#" target="_blank">Export CSV</a>
                </div>
            </div>
        </aside>
    </section>

    <div id="results-root" class="{{ !empty($currentRun) || !empty($results) ? '' : 'hidden' }} space-y-6">
        <section class="grid md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Visible Results</p><p id="summary-total" class="text-3xl font-semibold">{{ $summary['total'] ?? count($results) }}</p></div>
            <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Positive</p><p id="summary-success" class="text-3xl font-semibold text-emerald-700">{{ $summary['success'] ?? 0 }}</p></div>
            <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Errors</p><p id="summary-errors" class="text-3xl font-semibold text-amber-600">{{ $summary['errors'] ?? 0 }}</p></div>
            <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Skipped</p><p id="summary-skipped" class="text-3xl font-semibold text-slate-600">{{ $summary['skipped'] ?? 0 }}</p></div>
        </section>

        <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-3 text-sm text-slate-600">
            <div>Run persisted as <span id="summary-run-id" class="font-mono text-slate-900">{{ $summary['run_id'] ?? ($currentRun['id'] ?? '') }}</span></div>
            <div id="summary-expected" class="{{ empty($currentRun['expected_results'] ?? ($summary['meta']['expected_results'] ?? null)) ? 'hidden' : '' }}">
                Expected validator results:
                <span class="font-medium text-slate-900">{{ $currentRun['expected_results'] ?? ($summary['meta']['expected_results'] ?? '') }}</span>
            </div>
            <div id="summary-expanded" class="{{ empty($summary['meta']['expanded_targets'] ?? ($currentRun['expanded_targets'] ?? [])) ? 'hidden' : '' }}">
                Expanded targets scanned:
                <span class="font-medium text-slate-900">{{ count($summary['meta']['expanded_targets'] ?? ($currentRun['expanded_targets'] ?? [])) }}</span>
            </div>
            <div class="flex gap-3">
                <a id="summary-export-json" class="underline" href="{{ !empty($summary['run_id']) ? '/api/scanner/runs/' . $summary['run_id'] . '/export/json' : (!empty($currentRun['id']) ? '/api/scanner/runs/' . $currentRun['id'] . '/export/json' : '#') }}" target="_blank">Export JSON</a>
                <a id="summary-export-csv" class="underline" href="{{ !empty($summary['run_id']) ? '/api/scanner/runs/' . $summary['run_id'] . '/export/csv' : (!empty($currentRun['id']) ? '/api/scanner/runs/' . $currentRun['id'] . '/export/csv' : '#') }}" target="_blank">Export CSV</a>
            </div>
        </section>

        <div id="results-groups" class="space-y-6">
            @foreach(($resultsByCategory ?? []) as $category => $rows)
                <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/80 flex items-center justify-between">
                        <h3 class="text-xl font-display">{{ strtoupper($category) }} sites</h3>
                        <span class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ count($rows) }} results</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="text-left px-4 py-3">Target</th>
                                <th class="text-left px-4 py-3">Site</th>
                                <th class="text-left px-4 py-3">Status</th>
                                <th class="text-left px-4 py-3">Reason</th>
                                <th class="text-left px-4 py-3">Account Metadata</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @foreach($rows as $result)
                                @php $status = $result['status'] ?? ''; @endphp
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs md:text-sm">{{ $result['target'] ?? '' }}</td>
                                    <td class="px-4 py-3">
                                        @if(!empty($result['url']))
                                            <a href="{{ $result['url'] }}" class="text-teal-700 hover:underline" target="_blank">{{ $result['site_name'] ?? '' }}</a>
                                        @else
                                            {{ $result['site_name'] ?? '' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                                            {{ in_array($status, ['Found', 'Registered'], true) ? 'bg-emerald-100 text-emerald-700' : '' }}
                                            {{ in_array($status, ['Not Found', 'Not Registered'], true) ? 'bg-rose-100 text-rose-700' : '' }}
                                            {{ $status === 'Error' ? 'bg-amber-100 text-amber-700' : '' }}
                                            {{ $status === 'Skipped' ? 'bg-slate-200 text-slate-700' : '' }}">
                                            {{ $status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $result['reason'] ?? '' }}</td>
                                    <td class="px-4 py-3 text-slate-600 whitespace-pre-line">{{ $result['extra'] ?? '' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>

@php
    $bootPayload = [
        'run' => $currentRun ?? null,
        'results' => $results ?? [],
        'pollInterval' => config('scanner.async.poll_interval_ms', 2000),
    ];
@endphp

<script>
(() => {
    const boot = {!! json_encode($bootPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    const modeSelect = document.getElementById('single-mode');
    const categorySelect = document.getElementById('single-category');
    const moduleSelect = document.getElementById('single-module-keys');
    const noNsfwToggle = document.querySelector('input[name="no_nsfw"]');
    const batchMode = document.getElementById('batch-mode');
    const batchCategory = document.getElementById('batch-category');
    const batchModuleSelect = document.getElementById('batch-module-keys');
    const batchNoNsfwToggle = document.getElementById('batch-no-nsfw');
    const fileInput = document.getElementById('batch-target-file');
    const targetsArea = document.getElementById('batch-targets');
    const startBtn = document.getElementById('start-batch');
    const refreshBtn = document.getElementById('refresh-run');
    const statusBox = document.getElementById('run-status');
    const runIdEl = document.getElementById('run-id');
    const runMetaEl = document.getElementById('run-meta');
    const runProgress = document.getElementById('run-progress');
    const runProgressText = document.getElementById('run-progress-text');
    const exportJson = document.getElementById('export-json');
    const exportCsv = document.getElementById('export-csv');
    const resultsRoot = document.getElementById('results-root');
    const resultsGroups = document.getElementById('results-groups');
    const summaryRunId = document.getElementById('summary-run-id');
    const summaryExpected = document.getElementById('summary-expected');
    const summaryExpanded = document.getElementById('summary-expanded');
    const summaryTotal = document.getElementById('summary-total');
    const summarySuccess = document.getElementById('summary-success');
    const summaryErrors = document.getElementById('summary-errors');
    const summarySkipped = document.getElementById('summary-skipped');
    const summaryExportJson = document.getElementById('summary-export-json');
    const summaryExportCsv = document.getElementById('summary-export-csv');
    let currentRunId = null;
    let timer = null;

    function refillSelect(select, items, toValue, toLabel, includeAll = false) {
        const current = Array.from(select.selectedOptions).map(option => option.value);
        select.innerHTML = includeAll ? '<option value="">All categories</option>' : '';
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = toValue(item);
            option.textContent = toLabel(item);
            if (current.includes(option.value)) option.selected = true;
            select.appendChild(option);
        });
    }

    async function reloadCatalog(mode, noNsfw, categoryEl, moduleEl) {
        const res = await fetch(`/api/scanner/modules/${mode}?no_nsfw=${noNsfw ? 1 : 0}`);
        if (!res.ok) return;
        const data = await res.json();
        refillSelect(categoryEl, data.categories || [], item => item, item => item.charAt(0).toUpperCase() + item.slice(1), true);
        refillSelect(moduleEl, data.modules || [], item => item.key, item => `${item.category.charAt(0).toUpperCase() + item.category.slice(1)} / ${item.site_name}${item.loud ? ' [loud]' : ''}${item.nsfw ? ' [nsfw]' : ''}`);
    }

    function groupByCategory(results) {
        return results.reduce((carry, result) => {
            const category = (result.category || 'other').toLowerCase();
            if (!carry[category]) carry[category] = [];
            carry[category].push(result);
            return carry;
        }, {});
    }

    function statusClass(status) {
        if (['Found', 'Registered'].includes(status)) return 'bg-emerald-100 text-emerald-700';
        if (['Not Found', 'Not Registered'].includes(status)) return 'bg-rose-100 text-rose-700';
        if (status === 'Error') return 'bg-amber-100 text-amber-700';
        if (status === 'Skipped') return 'bg-slate-200 text-slate-700';
        return 'bg-slate-100 text-slate-700';
    }

    function formatExtra(value) {
        const text = escapeHtml(value).trim();

        return text ? `<div class="whitespace-pre-line">${text}</div>` : '';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function renderResults(run, results) {
        resultsRoot.classList.remove('hidden');
        const groups = groupByCategory(results);
        const positives = results.filter(row => ['Found', 'Registered'].includes(row.status)).length;
        const errors = results.filter(row => row.status === 'Error').length;
        const skipped = results.filter(row => row.status === 'Skipped').length;

        summaryRunId.textContent = run.id;
        summaryTotal.textContent = String(results.length);
        summarySuccess.textContent = String(positives);
        summaryErrors.textContent = String(errors);
        summarySkipped.textContent = String(skipped);
        summaryExportJson.href = `/api/scanner/runs/${run.id}/export/json`;
        summaryExportCsv.href = `/api/scanner/runs/${run.id}/export/csv`;
        summaryExpected.classList.toggle('hidden', !run.expected_results);
        if (summaryExpected.querySelector('span')) {
            summaryExpected.querySelector('span').textContent = String(run.expected_results || '');
        }
        summaryExpanded.classList.toggle('hidden', !(run.expanded_targets || []).length);
        if (summaryExpanded.querySelector('span')) {
            summaryExpanded.querySelector('span').textContent = String((run.expanded_targets || []).length);
        }

        const categories = Object.keys(groups).sort();
        resultsGroups.innerHTML = categories.map(category => {
            const rows = groups[category];
            return `
                <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/80 flex items-center justify-between">
                        <h3 class="text-xl font-display">${escapeHtml(category.toUpperCase())} sites</h3>
                        <span class="text-xs uppercase tracking-[0.2em] text-slate-500">${rows.length} results</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-slate-700">
                                <tr>
                                    <th class="text-left px-4 py-3">Target</th>
                                    <th class="text-left px-4 py-3">Site</th>
                                    <th class="text-left px-4 py-3">Status</th>
                                    <th class="text-left px-4 py-3">Reason</th>
                                    <th class="text-left px-4 py-3">Account Metadata</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                ${rows.map(row => `
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-xs md:text-sm">${escapeHtml(row.target)}</td>
                                        <td class="px-4 py-3">
                                            ${row.url ? `<a href="${escapeHtml(row.url)}" class="text-teal-700 hover:underline" target="_blank">${escapeHtml(row.site_name)}</a>` : escapeHtml(row.site_name)}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold ${statusClass(row.status)}">${escapeHtml(row.status)}</span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">${escapeHtml(row.reason)}</td>
                                        <td class="px-4 py-3 text-slate-600">${formatExtra(row.extra)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </section>
            `;
        }).join('');
    }

    async function pollRun() {
        if (!currentRunId) return;
        const res = await fetch(`/api/scanner/runs/${currentRunId}`);
        if (!res.ok) return;
        const data = await res.json();
        const run = data.run;

        statusBox.classList.remove('hidden');
        runIdEl.textContent = `Run: ${run.id}`;
        runMetaEl.textContent = `${run.status}`;
        runProgress.style.width = `${run.progress}%`;
        runProgressText.textContent = `${run.processed}/${run.total} processed (${run.progress}%) · queued ${run.queued_jobs} · running ${run.running_jobs}`;

        exportJson.href = `/api/scanner/runs/${run.id}/export/json`;
        exportCsv.href = `/api/scanner/runs/${run.id}/export/csv`;
        renderResults(run, data.results || []);

        if (run.status === 'completed' || run.status === 'failed') {
            clearInterval(timer);
            timer = null;
        }
    }

    modeSelect?.addEventListener('change', () => reloadCatalog(modeSelect.value, noNsfwToggle?.checked, categorySelect, moduleSelect));
    noNsfwToggle?.addEventListener('change', () => reloadCatalog(modeSelect.value, noNsfwToggle.checked, categorySelect, moduleSelect));
    batchMode?.addEventListener('change', () => reloadCatalog(batchMode.value, batchNoNsfwToggle?.checked, batchCategory, batchModuleSelect));
    batchNoNsfwToggle?.addEventListener('change', () => reloadCatalog(batchMode.value, batchNoNsfwToggle.checked, batchCategory, batchModuleSelect));

    fileInput?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        targetsArea.value = (await file.text()).trim();
    });

    startBtn?.addEventListener('click', async () => {
        const targets = targetsArea.value.split('\n').map(v => v.trim()).filter(Boolean);
        if (!targets.length) {
            alert('Enter at least one target.');
            return;
        }

        const res = await fetch('/api/scanner/runs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                mode: batchMode.value,
                category: batchCategory.value || null,
                module_keys: Array.from(batchModuleSelect.selectedOptions).map(option => option.value),
                targets,
                only_found: document.getElementById('batch-only-found').checked,
                allow_loud: document.getElementById('batch-allow-loud').checked,
                no_nsfw: document.getElementById('batch-no-nsfw').checked,
                validate_proxies: document.getElementById('batch-validate-proxies').checked,
                proxy_list: document.getElementById('batch-proxy-list').value,
                use_proxy: document.getElementById('batch-proxy-list').value.trim().length > 0,
                stop: Number(document.getElementById('batch-stop').value || 100),
                delay: Number(document.getElementById('batch-delay').value || 0),
            }),
        });

        const data = await res.json();
        if (!res.ok || !data.ok) {
            alert(data.error || 'Failed to create run');
            return;
        }

        currentRunId = data.run_id;
        await pollRun();
        if (!timer) timer = setInterval(pollRun, boot.pollInterval || 2000);
    });

    refreshBtn?.addEventListener('click', async () => {
        const res = await fetch('/api/scanner/runs');
        const data = await res.json();
        const latest = (data.runs || [])[0];
        if (!latest) {
            alert('No runs found');
            return;
        }
        currentRunId = latest.id;
        await pollRun();
        if (!timer) timer = setInterval(pollRun, boot.pollInterval || 2000);
    });

    if (boot.run?.id) {
        currentRunId = boot.run.id;
        statusBox.classList.remove('hidden');
        runIdEl.textContent = `Run: ${boot.run.id}`;
        runMetaEl.textContent = `${boot.run.status}`;
        runProgress.style.width = `${boot.run.total ? ((boot.run.processed / boot.run.total) * 100) : 0}%`;
        runProgressText.textContent = `${boot.run.processed}/${boot.run.total} processed`;
        exportJson.href = `/api/scanner/runs/${boot.run.id}/export/json`;
        exportCsv.href = `/api/scanner/runs/${boot.run.id}/export/csv`;
        renderResults(boot.run, boot.results || []);
        if (!['completed', 'failed'].includes(boot.run.status)) {
            timer = setInterval(pollRun, boot.pollInterval || 2000);
        }
    }
})();
</script>
</body>
</html>
