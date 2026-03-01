@extends('layouts.app', ['title' => 'Scan Detail'])

@section('content')
<section class="rounded-xl bg-white p-6 shadow-sm">
    <h2 class="text-xl font-semibold">Scan #{{ $scan->id }}</h2>
    <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
        <p><span class="font-medium">Type:</span> {{ $scan->type->value ?? $scan->type }}</p>
        <p><span class="font-medium">Target:</span> {{ $scan->target }}</p>
        <p><span class="font-medium">Status:</span> {{ $scan->status->value ?? $scan->status }}</p>
        <p><span class="font-medium">Progress:</span> {{ $scan->processed_items }} / {{ $scan->total_items }}</p>
    </div>
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('scans.export', [$scan, 'json']) }}" class="rounded bg-slate-800 px-3 py-1.5 text-sm font-medium text-white">Export JSON</a>
        <a href="{{ route('scans.export', [$scan, 'csv']) }}" class="rounded bg-slate-700 px-3 py-1.5 text-sm font-medium text-white">Export CSV</a>
        @if (in_array($scan->status->value ?? $scan->status, ['queued', 'running'], true))
            <form method="POST" action="{{ route('scans.cancel', $scan) }}">
                @csrf
                <button type="submit" class="rounded bg-rose-600 px-3 py-1.5 text-sm font-medium text-white">Cancel Scan</button>
            </form>
        @endif
    </div>

</section>

<section class="mt-6 rounded-xl bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Results</h3>
    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr>
                    <th class="py-2">Connector</th>
                    <th class="py-2">Category</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Confidence</th>
                    <th class="py-2">Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($scan->results as $result)
                    <tr class="border-t">
                        <td class="py-2">{{ $result->connector_key }}</td>
                        <td class="py-2">{{ $result->category }}</td>
                        <td class="py-2">{{ $result->status->value ?? $result->status }}</td>
                        <td class="py-2">{{ $result->confidence }}</td>
                        <td class="py-2">{{ $result->reason }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-slate-500">No result rows yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
