<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Scanner (Laravel)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900">
<div class="max-w-7xl mx-auto py-10 px-4 space-y-6">
    <div>
        <h1 class="text-3xl font-bold">User Scanner</h1>
        <p class="text-slate-600 mt-1">Single scans, async batch runs, persistence, filtering, and export.</p>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
            <p class="font-semibold text-rose-700 mb-1">Please fix the following:</p>
            <ul class="list-disc ml-5 text-sm text-rose-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('scanner.run') }}" class="bg-white rounded-xl shadow p-6 space-y-4">
        @csrf
        <h2 class="text-lg font-semibold">Single Scan</h2>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Mode</label>
                <select name="mode" id="single-mode" class="w-full rounded border-slate-300" required>
                    <option value="username" @selected(($input['mode'] ?? 'username') === 'username')>Username</option>
                    <option value="email" @selected(($input['mode'] ?? '') === 'email')>Email</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                <input type="text" name="category" value="{{ $input['category'] ?? '' }}" class="w-full rounded border-slate-300" placeholder="social, dev...">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Target</label>
                <input type="text" name="target" value="{{ $input['target'] ?? '' }}" class="w-full rounded border-slate-300" required placeholder="johndoe or john@example.com">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Module keys (comma-separated)</label>
            <input list="module-keys" type="text" name="module_keys" value="{{ $input['module_keys'] ?? '' }}" class="w-full rounded border-slate-300" placeholder="github,x">
            <datalist id="module-keys">
                @foreach(($moduleCatalog ?? []) as $mod)
                    <option value="{{ $mod['key'] }}">{{ $mod['category'] }} / {{ $mod['site_name'] }}</option>
                @endforeach
            </datalist>
        </div>

        <div class="grid md:grid-cols-2 gap-4 items-start">
            <label class="inline-flex items-center gap-2 mt-2">
                <input type="checkbox" name="use_proxy" value="1" @checked(($input['use_proxy'] ?? false))>
                <span class="text-sm text-slate-700">Use proxy rotation</span>
            </label>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Proxy list (optional)</label>
                <textarea name="proxy_list" rows="3" class="w-full rounded border-slate-300" placeholder="http://127.0.0.1:8080&#10;socks5://127.0.0.1:9050">{{ $input['proxy_list'] ?? '' }}</textarea>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">Run Single Scan</button>
    </form>

    <div class="bg-white rounded-xl shadow p-6 space-y-3">
        <h2 class="text-lg font-semibold">Batch Scan (Async Queue)</h2>
        <p class="text-sm text-slate-600">One target per line. This creates a persisted run and processes targets via queue jobs.</p>

        <div class="grid md:grid-cols-2 gap-3">
            <select id="batch-mode" class="rounded border-slate-300">
                <option value="username">Username</option>
                <option value="email">Email</option>
            </select>
            <input id="batch-category" class="rounded border-slate-300" placeholder="category (optional)">
        </div>

        <textarea id="batch-targets" rows="5" class="w-full rounded border-slate-300" placeholder="alice&#10;bob&#10;charlie@example.com"></textarea>

        <div class="flex gap-2">
            <button id="start-batch" type="button" class="px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">Start Batch</button>
            <button id="refresh-run" type="button" class="px-4 py-2 rounded bg-slate-600 text-white hover:bg-slate-700">Refresh Latest</button>
        </div>

        <div id="run-status" class="hidden">
            <div class="text-sm text-slate-700 mb-1"><span id="run-id"></span> · <span id="run-meta"></span></div>
            <div class="w-full bg-slate-200 rounded h-3 overflow-hidden">
                <div id="run-progress" class="h-3 bg-indigo-600" style="width:0%"></div>
            </div>
            <div class="mt-2 text-xs text-slate-600" id="run-progress-text"></div>
            <div class="mt-2 flex gap-2">
                <a id="export-json" class="text-xs text-indigo-700 underline" href="#" target="_blank">Export JSON</a>
                <a id="export-csv" class="text-xs text-indigo-700 underline" href="#" target="_blank">Export CSV</a>
            </div>
        </div>
    </div>

    @if(!empty($results))
        <div class="grid md:grid-cols-3 gap-3">
            <div class="bg-white rounded-lg p-4 shadow"><p class="text-xs uppercase text-slate-500">Total</p><p class="text-2xl font-semibold">{{ $summary['total'] ?? count($results) }}</p></div>
            <div class="bg-white rounded-lg p-4 shadow"><p class="text-xs uppercase text-slate-500">Resolved</p><p class="text-2xl font-semibold text-emerald-600">{{ $summary['success'] ?? 0 }}</p></div>
            <div class="bg-white rounded-lg p-4 shadow"><p class="text-xs uppercase text-slate-500">Errors</p><p class="text-2xl font-semibold text-amber-600">{{ $summary['errors'] ?? 0 }}</p></div>
        </div>

        @if(!empty($summary['run_id']))
            <div class="text-sm text-slate-600">Run persisted as <span class="font-mono">{{ $summary['run_id'] }}</span> ·
                <a class="text-indigo-700 underline" href="/api/scanner/runs/{{ $summary['run_id'] }}/export/json" target="_blank">Export JSON</a>
                · <a class="text-indigo-700 underline" href="/api/scanner/runs/{{ $summary['run_id'] }}/export/csv" target="_blank">Export CSV</a>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 text-sm text-slate-700">Single scan results</div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-700"><tr><th class="text-left px-4 py-3">Target</th><th class="text-left px-4 py-3">Category</th><th class="text-left px-4 py-3">Site</th><th class="text-left px-4 py-3">Status</th><th class="text-left px-4 py-3">Reason</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach($results as $result)
                        <tr>
                            <td class="px-4 py-3">{{ $result['target'] ?? '' }}</td>
                            <td class="px-4 py-3">{{ ucfirst((string)($result['category'] ?? '')) }}</td>
                            <td class="px-4 py-3"><a href="{{ $result['url'] ?? '#' }}" class="text-indigo-600 hover:underline" target="_blank">{{ $result['site_name'] ?? '' }}</a></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 rounded text-xs font-medium {{ in_array(($result['status'] ?? ''), ['Available', 'Registered']) ? 'bg-emerald-100 text-emerald-700' : '' }} {{ in_array(($result['status'] ?? ''), ['Taken', 'Not Registered']) ? 'bg-rose-100 text-rose-700' : '' }} {{ ($result['status'] ?? '') === 'Error' ? 'bg-amber-100 text-amber-700' : '' }}">{{ $result['status'] ?? '' }}</span></td>
                            <td class="px-4 py-3 text-slate-600">{{ $result['reason'] ?? '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<script>
(() => {
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

    startBtn?.addEventListener('click', async () => {
        const mode = document.getElementById('batch-mode').value;
        const category = document.getElementById('batch-category').value.trim();
        const targets = document.getElementById('batch-targets').value
            .split('\n')
            .map(v => v.trim())
            .filter(Boolean);

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
            body: JSON.stringify({ mode, category: category || null, targets }),
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
