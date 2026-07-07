<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OpsMetricsService;
use Illuminate\Http\Request;

final class OpsMetricsController extends Controller
{
    public function index(Request $request, OpsMetricsService $metrics)
    {
        $window = (string) $request->query('window', '30d');

        $metrics->ensureRecentQueueSnapshot();

        return view('ops.metrics', [
            'dashboard' => $metrics->dashboard($window),
            'windowPresets' => OpsMetricsService::WINDOW_PRESETS,
        ]);
    }
}
