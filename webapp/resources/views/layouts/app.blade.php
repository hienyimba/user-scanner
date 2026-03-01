<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'User Scanner' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <h1 class="text-lg font-semibold">User Scanner</h1>
            <nav class="flex items-center gap-4 text-sm">
                <a class="text-slate-700 hover:text-slate-900" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="rounded bg-indigo-600 px-3 py-1.5 font-medium text-white hover:bg-indigo-500" href="{{ route('scans.create') }}">New Scan</a>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>
</body>
</html>
