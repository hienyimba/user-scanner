@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
<div class="grid gap-4 md:grid-cols-3">
    <section class="rounded-xl bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Total scans</p>
        <p class="mt-2 text-3xl font-semibold">{{ $totalScans }}</p>
    </section>
    <section class="rounded-xl bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Completed scans</p>
        <p class="mt-2 text-3xl font-semibold">{{ $completedScans }}</p>
    </section>
    <section class="rounded-xl bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-500">Error rate</p>
        <p class="mt-2 text-3xl font-semibold">{{ $errorRate }}%</p>
    </section>
</div>

<section class="mt-8 rounded-xl bg-white p-5 shadow-sm">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Recent scans</h2>
        <a href="{{ route('scans.create') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Run a scan</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-slate-500">
                <tr>
                    <th class="py-2">ID</th>
                    <th class="py-2">Type</th>
                    <th class="py-2">Target</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentScans as $scan)
                    <tr class="border-t">
                        <td class="py-2">#{{ $scan->id }}</td>
                        <td class="py-2">{{ $scan->type->value ?? $scan->type }}</td>
                        <td class="py-2">{{ $scan->target }}</td>
                        <td class="py-2">{{ $scan->status->value ?? $scan->status }}</td>
                        <td class="py-2">{{ $scan->created_at }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 text-slate-500">No scans yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
