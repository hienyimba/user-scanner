<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ScanBatch;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $ttl = max(10, (int) config('scanner.performance.dashboard_cache_ttl_seconds', 30));

        $stats = Cache::remember('scanner:dashboard:stats:v1', $ttl, function (): array {
            $totalScans = ScanBatch::query()->count();
            $errors = ScanBatch::query()->where('error_count', '>', 0)->count();
            $completed = ScanBatch::query()->where('status', 'completed')->count();

            return [
                'totalScans' => $totalScans,
                'completedScans' => $completed,
                'errorRate' => $totalScans > 0 ? round(($errors / $totalScans) * 100, 2) : 0,
            ];
        });

        $recentScans = Cache::remember('scanner:dashboard:recent:v1', $ttl, function () {
            return ScanBatch::query()
                ->select(['id', 'type', 'target', 'status', 'created_at'])
                ->latest()
                ->take(10)
                ->get();
        });

        return view('dashboard.index', $stats + ['recentScans' => $recentScans]);
    }
}
