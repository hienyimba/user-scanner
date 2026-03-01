@extends('layouts.app', ['title' => 'New Scan'])

@section('content')
<section class="rounded-xl bg-white p-6 shadow-sm">
    <h2 class="text-xl font-semibold">Run a New Scan</h2>
    <p class="mt-1 text-sm text-slate-500">Single username or email scan with optional module settings.</p>

    <form action="{{ route('scans.store') }}" method="POST" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="type" class="mb-1 block text-sm font-medium">Scan type</label>
            <select name="type" id="type" class="w-full rounded border border-slate-300 px-3 py-2" required>
                <option value="username" @selected(old('type') === 'username')>Username</option>
                <option value="email" @selected(old('type') === 'email')>Email</option>
            </select>
        </div>

        <div>
            <label for="target" class="mb-1 block text-sm font-medium">Single target</label>
            <input name="target" id="target" value="{{ old('target') }}" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="john_doe or john@example.com">
            <p class="mt-1 text-xs text-slate-500">Use single target OR bulk targets below.</p>
        </div>

        <div>
            <label for="targets" class="mb-1 block text-sm font-medium">Bulk targets (one per line)</label>
            <textarea id="targets" rows="4" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="alice\nbob\ncarol@example.com"></textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="category" class="mb-1 block text-sm font-medium">Category (optional)</label>
                <input name="category" id="category" value="{{ old('category') }}" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="social, dev, creator...">
            </div>
            <div>
                <label for="module" class="mb-1 block text-sm font-medium">Module (optional)</label>
                <input name="module" id="module" value="{{ old('module') }}" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="github, reddit...">
            </div>
        </div>

        <div>
            <label for="proxy_profile" class="mb-1 block text-sm font-medium">Proxy profile (optional)</label>
            <input name="proxy_profile" id="proxy_profile" value="{{ old('proxy_profile') }}" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="default-rotating">
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="retry_limit" class="mb-1 block text-sm font-medium">Retry limit</label>
                <input type="number" min="1" max="5" name="retry_limit" id="retry_limit" value="{{ old('retry_limit', 3) }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label for="timeout_seconds" class="mb-1 block text-sm font-medium">Timeout seconds</label>
                <input type="number" min="2" max="60" name="timeout_seconds" id="timeout_seconds" value="{{ old('timeout_seconds', 20) }}" class="w-full rounded border border-slate-300 px-3 py-2">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="verbose" value="1" @checked(old('verbose'))>
            Include verbose output (URLs and reasons)
        </label>

        @if ($errors->any())
            <div class="rounded border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500">Queue scan</button>
    </form>
</section>

<script>
    const textarea = document.getElementById('targets');
    const form = textarea?.closest('form');
    form?.addEventListener('submit', () => {
        const lines = textarea.value.split('\n').map(v => v.trim()).filter(Boolean);
        document.querySelectorAll('input[name="targets[]"]').forEach((el) => el.remove());
        lines.forEach((line) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'targets[]';
            input.value = line;
            form.appendChild(input);
        });
    });
</script>
@endsection
