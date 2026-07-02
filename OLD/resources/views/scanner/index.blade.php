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
                <p class="text-slate-600 max-w-2xl">Single scans, queued batch runs, proxy controls, pattern expansion, module filters, exports, and grouped results in one Laravel-first workflow.</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    <span class="rounded-full bg-slate-900 text-white px-3 py-1">Username + Email</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Found / Not Found parity</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1">Pattern + Proxy aware</span>
                </div>
            </div>
            <div class="rounded-2xl bg-slate-900 text-slate-100 p-5 space-y-3">
                <h2 class="text-lg font-semibold">Behavior Notes</h2>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li>Username scans now surface <span class="text-white">Found</span> and <span class="text-white">Not Found</span>.</li>
                    <li>Email scans preserve <span class="text-white">Registered</span> and <span class="text-white">Not Registered</span>.</li>
                    <li>Loud email modules are skipped by default unless you enable them.</li>
                    <li>Adult categories remain included unless you explicitly exclude NSFW.</li>
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
                    <h2 class="text-2xl font-display">Single Scan</h2>
                    <p class="text-sm text-slate-600 mt-1">Works without JavaScript for the core Laravel UI flow.</p>
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

            <button type="submit" class="inline-flex items-center px-5 py-3 rounded-full bg-slate-900 text-white hover:bg-slate-800">Run Single Scan</button>
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
                    <textarea id="batch-proxy-list" rows="3" class="w-full rounded-2xl border-slate-300" placeholder="Optional proxy list"></textarea>
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

            <div id="run-status" class="hidden rounded-3xl bg-slate-900 text-slate-100 p-6 shadow-xl">
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

    @if(!empty($results))
        <section class="grid md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Results</p><p class="text-3xl font-semibold">{{ $summary['total'] ?? count($results) }}</p></div>
            <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Positive</p><p class="text-3xl font-semibold text-emerald-700">{{ $summary['success'] ?? 0 }}</p></div>
            <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Errors</p><p class="text-3xl font-semibold text-amber-600">{{ $summary['errors'] ?? 0 }}</p></div>
            <div class="rounded-2xl bg-white/90 p-5 shadow"><p class="text-xs uppercase tracking-[0.2em] text-slate-500">Skipped</p><p class="text-3xl font-semibold text-slate-600">{{ $summary['skipped'] ?? 0 }}</p></div>
        </section>

        <section class="rounded-3xl bg-white/85 backdrop-blur border border-white/70 shadow-xl p-6 space-y-3 text-sm text-slate-600">
            <div>Run persisted as <span class="font-mono text-slate-900">{{ $summary['run_id'] ?? '' }}</span></div>
            @if(!empty($summary['meta']['total_permutations']))
                <div>Pattern permutations: <span class="font-medium text-slate-900">{{ $summary['meta']['total_permutations'] }}</span></div>
            @endif
            @if(!empty($summary['meta']['expanded_targets']))
                <div>Expanded targets scanned: <span class="font-medium text-slate-900">{{ count($summary['meta']['expanded_targets']) }}</span></div>
            @endif
            <div class="flex gap-3">
                <a class="underline" href="/api/scanner/runs/{{ $summary['run_id'] }}/export/json" target="_blank">Export JSON</a>
                <a class="underline" href="/api/scanner/runs/{{ $summary['run_id'] }}/export/csv" target="_blank">Export CSV</a>
            </div>
        </section>

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
                            <th class="text-left px-4 py-3">Extra</th>
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
                                <td class="px-4 py-3 text-slate-600">{{ $result['extra'] ?? '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</div>

<script>
(() => {
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
        runProgressText.textContent = `${run.processed}/${run.total} processed (${run.progress}%)`;

        exportJson.href = `/api/scanner/runs/${run.id}/export/json`;
        exportCsv.href = `/api/scanner/runs/${run.id}/export/csv`;

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
        if (!timer) timer = setInterval(pollRun, 2000);
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
        if (!timer) timer = setInterval(pollRun, 2000);
    });
})();
</script>
</body>
</html>
