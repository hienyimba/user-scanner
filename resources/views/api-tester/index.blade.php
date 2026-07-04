<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner API Tester</title>
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
<body class="min-h-screen bg-[linear-gradient(135deg,_#fff7ed,_#f8fafc_55%,_#dbeafe)] text-slate-900 font-body">
<div class="max-w-7xl mx-auto px-4 py-10 space-y-8">
    <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl overflow-hidden">
        <div class="grid lg:grid-cols-[1.15fr_0.85fr] gap-6 p-6 md:p-8">
            <div class="space-y-4">
                <p class="text-xs uppercase tracking-[0.3em] text-orange-700 font-semibold">Public API Tester</p>
                <h1 class="font-display text-4xl md:text-5xl leading-tight">Exercise the scan API exactly the way external callers will.</h1>
                <p class="text-slate-600 max-w-2xl">Submit one target, get back a run id, and watch grouped module results update live until the job finishes.</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    <span class="rounded-full bg-slate-900 text-white px-3 py-1">POST /api/v1/scan</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">GET /api/v1/scan/{runId}</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Live Polling</span>
                </div>
            </div>
            <div class="rounded-2xl bg-slate-900 text-slate-100 p-5 space-y-3">
                <h2 class="text-lg font-semibold">v1 Contract</h2>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li>Required fields: <span class="text-white">mode</span>, <span class="text-white">target</span></li>
                    <li>Optional fields: <span class="text-white">category</span>, <span class="text-white">use_proxy</span>, <span class="text-white">show_hits</span></li>
                    <li>`show_hits` maps to found/registered-only filtering in the response.</li>
                    <li>Single-target contract only for v1.</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="grid xl:grid-cols-[0.95fr_1.05fr] gap-6 items-start">
        <div class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 md:p-8 space-y-6">
            <div>
                <h2 class="text-2xl font-display">API Request</h2>
                <p class="text-sm text-slate-600 mt-1">This page calls the public API, not the internal run endpoints.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mode</label>
                    <select id="api-mode" class="w-full rounded-xl border-slate-300">
                        <option value="username">Username</option>
                        <option value="email">Email</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                    <select id="api-category" class="w-full rounded-xl border-slate-300">
                        <option value="">All categories</option>
                        @foreach($categoryCatalog as $category)
                            <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Target</label>
                <input id="api-target" type="text" class="w-full rounded-xl border-slate-300" placeholder="johndoe or jane@example.com">
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input id="api-use-proxy" type="checkbox" @checked($defaultProxyList !== '')>
                    <span class="text-sm">Use configured proxies</span>
                </label>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input id="api-show-hits" type="checkbox">
                    <span class="text-sm">Show hits only</span>
                </label>
            </div>

            <label class="flex items-center gap-3 rounded-2xl border border-orange-200 bg-orange-50 px-4 py-3">
                <input id="api-june-only" type="checkbox">
                <span class="text-sm">June-only modules</span>
            </label>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Configured Proxy List</label>
                <textarea rows="4" class="w-full rounded-2xl border-slate-300 bg-slate-50 text-slate-600" readonly>{{ $defaultProxyList }}</textarea>
            </div>

            <div class="space-y-2">
                <div class="text-sm font-medium text-slate-700">Request Preview</div>
                <pre id="request-preview" class="rounded-2xl bg-slate-950 text-slate-100 p-4 text-xs overflow-x-auto"></pre>
            </div>

            <div class="flex gap-3">
                <button id="submit-request" type="button" class="px-5 py-3 rounded-full bg-orange-700 text-white hover:bg-orange-800">Start API Scan</button>
                <button id="poll-latest" type="button" class="px-5 py-3 rounded-full bg-slate-700 text-white hover:bg-slate-800">Poll Current Run</button>
            </div>
        </div>

        <div class="space-y-6">
            <section id="run-panel" class="hidden rounded-3xl bg-slate-900 text-slate-100 p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Run</div>
                        <div id="run-summary" class="mt-1 text-sm text-slate-200"></div>
                    </div>
                    <div id="run-state" class="px-3 py-1 rounded-full bg-slate-700 text-xs font-semibold uppercase tracking-[0.2em]"></div>
                </div>
                <div class="mt-4 w-full bg-slate-700 rounded h-3 overflow-hidden">
                    <div id="run-progress" class="h-3 bg-emerald-500" style="width:0%"></div>
                </div>
                <div id="run-progress-text" class="mt-2 text-xs text-slate-300"></div>
            </section>

            <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-xl font-display">Raw API Response</h3>
                    <div id="run-id-chip" class="hidden rounded-full bg-slate-900 text-white px-3 py-1 text-xs font-mono"></div>
                </div>
                <pre id="raw-response" class="rounded-2xl bg-slate-950 text-slate-100 p-4 text-xs overflow-x-auto min-h-[180px]"></pre>
            </section>

            <div id="results-root" class="hidden space-y-6">
                <section class="grid md:grid-cols-4 gap-4">
                    <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Visible Results</p><p id="summary-total" class="text-3xl font-semibold">0</p></div>
                    <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Positive</p><p id="summary-success" class="text-3xl font-semibold text-emerald-700">0</p></div>
                    <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Errors</p><p id="summary-errors" class="text-3xl font-semibold text-amber-600">0</p></div>
                    <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Skipped</p><p id="summary-skipped" class="text-3xl font-semibold text-slate-600">0</p></div>
                </section>

                <div id="results-groups" class="space-y-6"></div>
            </div>
        </div>
    </section>
</div>

@php
    $bootPayload = [
        'moduleCatalog' => $moduleCatalog,
        'categoryCatalog' => $categoryCatalog,
        'juneOnlyModuleKeys' => $juneOnlyModuleKeys,
        'pollInterval' => config('scanner.async.poll_interval_ms', 2000),
    ];
@endphp

<script>
(() => {
    const boot = {!! json_encode($bootPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    const modeEl = document.getElementById('api-mode');
    const categoryEl = document.getElementById('api-category');
    const targetEl = document.getElementById('api-target');
    const useProxyEl = document.getElementById('api-use-proxy');
    const showHitsEl = document.getElementById('api-show-hits');
    const juneOnlyEl = document.getElementById('api-june-only');
    const requestPreviewEl = document.getElementById('request-preview');
    const submitBtn = document.getElementById('submit-request');
    const pollBtn = document.getElementById('poll-latest');
    const runPanel = document.getElementById('run-panel');
    const runSummary = document.getElementById('run-summary');
    const runState = document.getElementById('run-state');
    const runProgress = document.getElementById('run-progress');
    const runProgressText = document.getElementById('run-progress-text');
    const rawResponse = document.getElementById('raw-response');
    const runIdChip = document.getElementById('run-id-chip');
    const resultsRoot = document.getElementById('results-root');
    const resultsGroups = document.getElementById('results-groups');
    const summaryTotal = document.getElementById('summary-total');
    const summarySuccess = document.getElementById('summary-success');
    const summaryErrors = document.getElementById('summary-errors');
    const summarySkipped = document.getElementById('summary-skipped');

    let currentRunId = null;
    let timer = null;
    let currentEndpointMode = 'public';

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function statusClass(status) {
        if (['Found', 'Registered'].includes(status)) return 'bg-emerald-100 text-emerald-700';
        if (['Not Found', 'Not Registered'].includes(status)) return 'bg-rose-100 text-rose-700';
        if (status === 'Error') return 'bg-amber-100 text-amber-700';
        if (status === 'Skipped') return 'bg-slate-200 text-slate-700';
        return 'bg-slate-100 text-slate-700';
    }

    function refillCategories(items) {
        const current = categoryEl.value;
        categoryEl.innerHTML = '<option value="">All categories</option>';
        (items || []).forEach(item => {
            const option = document.createElement('option');
            option.value = item;
            option.textContent = item.charAt(0).toUpperCase() + item.slice(1);
            if (item === current) option.selected = true;
            categoryEl.appendChild(option);
        });
    }

    function buildPayload() {
        const payload = {
            mode: modeEl.value,
            category: categoryEl.value || null,
            target: targetEl.value.trim(),
            use_proxy: useProxyEl.checked,
            show_hits: showHitsEl.checked,
        };

        if (juneOnlyEl.checked) {
            return {
                mode: payload.mode,
                category: payload.category,
                targets: payload.target ? [payload.target] : [],
                module_keys: boot.juneOnlyModuleKeys?.[payload.mode] || [],
                use_proxy: payload.use_proxy,
                only_found: payload.show_hits,
                show_hits: payload.show_hits,
                stop: 1,
            };
        }

        return payload;
    }

    function renderRequestPreview() {
        requestPreviewEl.textContent = JSON.stringify(buildPayload(), null, 2);
    }

    function groupByCategory(results) {
        return results.reduce((carry, result) => {
            const category = (result.category || 'other').toLowerCase();
            if (!carry[category]) carry[category] = [];
            carry[category].push(result);
            return carry;
        }, {});
    }

    function renderResults(results) {
        resultsRoot.classList.remove('hidden');

        const positives = results.filter(row => ['Found', 'Registered'].includes(row.status)).length;
        const errors = results.filter(row => row.status === 'Error').length;
        const skipped = results.filter(row => row.status === 'Skipped').length;
        const grouped = groupByCategory(results);

        summaryTotal.textContent = String(results.length);
        summarySuccess.textContent = String(positives);
        summaryErrors.textContent = String(errors);
        summarySkipped.textContent = String(skipped);

        const categories = Object.keys(grouped).sort();
        resultsGroups.innerHTML = categories.map(category => {
            const rows = grouped[category];
            return `
                <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/80 flex items-center justify-between">
                        <h3 class="text-xl font-display">${escapeHtml(category.toUpperCase())} modules</h3>
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
                                    <th class="text-left px-4 py-3">Extra</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                ${rows.map(row => `
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-xs md:text-sm">${escapeHtml(row.target)}</td>
                                        <td class="px-4 py-3">
                                            ${row.url ? `<a href="${escapeHtml(row.url)}" class="text-teal-700 hover:underline" target="_blank">${escapeHtml(row.site_name)}</a>` : escapeHtml(row.site_name)}
                                        </td>
                                        <td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold ${statusClass(row.status)}">${escapeHtml(row.status)}</span></td>
                                        <td class="px-4 py-3 text-slate-600">${escapeHtml(row.reason)}</td>
                                        <td class="px-4 py-3 text-slate-600">${escapeHtml(row.extra)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </section>
            `;
        }).join('');
    }

    async function loadCategories(mode) {
        const endpoint = juneOnlyEl.checked ? `/api/scanner/modules/${mode}` : `/api/v1/scan/modules/${mode}`;
        const response = await fetch(endpoint);
        if (!response.ok) return;
        const data = await response.json();
        refillCategories(data.categories || []);
    }

    async function pollRun() {
        if (!currentRunId) return;

        const endpoint = currentEndpointMode === 'june-only'
            ? `/api/scanner/runs/${currentRunId}`
            : `/api/v1/scan/${currentRunId}`;
        const response = await fetch(endpoint);
        const data = await response.json();
        rawResponse.textContent = JSON.stringify(data, null, 2);

        if (!response.ok || !data.ok) {
            runPanel.classList.remove('hidden');
            runState.textContent = 'failed';
            runSummary.textContent = data.error || 'Request failed';
            clearInterval(timer);
            timer = null;
            return;
        }

        const run = data.run;
        runPanel.classList.remove('hidden');
        runIdChip.classList.remove('hidden');
        runIdChip.textContent = run.id;
        const targetLabel = run.target ?? run.options?.targets?.[0] ?? '';
        const categoryLabel = run.category ?? run.options?.category ?? null;
        runSummary.textContent = `${run.mode} scan for ${targetLabel}${categoryLabel ? ` in ${categoryLabel}` : ''}`;
        runState.textContent = run.status;
        runProgress.style.width = `${run.progress}%`;
        runProgressText.textContent = `${run.processed}/${run.total} processed (${run.progress}%)`;
        renderResults(data.results || []);

        if (['completed', 'failed'].includes(run.status)) {
            clearInterval(timer);
            timer = null;
        }
    }

    async function submitRequest() {
        const payload = buildPayload();
        const target = juneOnlyEl.checked ? (payload.targets?.[0] || '') : payload.target;
        if (!target) {
            alert('Please enter a target.');
            return;
        }

        const endpoint = juneOnlyEl.checked ? '/api/scanner/runs' : '/api/v1/scan';
        currentEndpointMode = juneOnlyEl.checked ? 'june-only' : 'public';
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();
        rawResponse.textContent = JSON.stringify(data, null, 2);

        if (!response.ok || !data.ok) {
            alert(data.error || 'Failed to start run');
            return;
        }

        currentRunId = data.run_id;
        runIdChip.classList.remove('hidden');
        runIdChip.textContent = data.run_id;
        await pollRun();
        if (!timer) {
            timer = setInterval(pollRun, boot.pollInterval || 2000);
        }
    }

    modeEl.addEventListener('change', async () => {
        await loadCategories(modeEl.value);
        renderRequestPreview();
    });
    juneOnlyEl.addEventListener('change', async () => {
        await loadCategories(modeEl.value);
        renderRequestPreview();
    });

    [categoryEl, targetEl, useProxyEl, showHitsEl, juneOnlyEl].forEach(element => {
        element.addEventListener('input', renderRequestPreview);
        element.addEventListener('change', renderRequestPreview);
    });

    submitBtn.addEventListener('click', submitRequest);
    pollBtn.addEventListener('click', async () => {
        if (!currentRunId) {
            alert('No current run to poll yet.');
            return;
        }
        await pollRun();
    });

    renderRequestPreview();
})();
</script>
</body>
</html>
