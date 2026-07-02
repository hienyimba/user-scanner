<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RunScanRequest;
use App\Services\Scanner\QueuedScanService;
use App\Services\Scanner\ScannerEngineService;
use Illuminate\Http\JsonResponse;

final class ScanController extends Controller
{
    public function run(RunScanRequest $request, ScannerEngineService $engine, QueuedScanService $queuedRuns): JsonResponse
    {
        $validated = $request->validated();
        $options = $queuedRuns->prepareOptions($validated);

        $scan = $engine->scanWithMeta(
            target: $validated['target'],
            mode: $validated['mode'],
            category: $validated['category'] ?? null,
            moduleKeys: $validated['module_keys'] ?? null,
            options: $options
        );

        return response()->json([
            'ok' => true,
            'meta' => [
                'mode' => $validated['mode'],
                'target' => $validated['target'],
                'count' => count($scan['results']),
                'scan' => $scan['meta'],
            ],
            'results' => array_map(static fn ($r) => $r->toArray(), $scan['results']),
        ]);
    }

    public function modules(string $mode, ScannerEngineService $engine): JsonResponse
    {
        abort_unless(in_array($mode, ['username', 'email'], true), 404);

        $noNsfw = request()->boolean('no_nsfw', false);

        return response()->json([
            'ok' => true,
            'mode' => $mode,
            'categories' => $engine->listCategories($mode, $noNsfw),
            'modules' => $engine->listModules($mode, $noNsfw),
        ]);
    }
}
