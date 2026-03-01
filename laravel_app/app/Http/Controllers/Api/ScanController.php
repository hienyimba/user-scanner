<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RunScanRequest;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\ScannerEngineService;
use Illuminate\Http\JsonResponse;

final class ScanController extends Controller
{
    public function run(RunScanRequest $request, ScannerEngineService $engine, ProxyManagerService $proxyManager): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['proxy_list'])) {
            $proxyManager->loadFromText($validated['proxy_list']);
        }

        $moduleKeys = null;
        if (!empty($validated['module_keys'])) {
            $moduleKeys = array_values(array_filter(array_map('trim', explode(',', $validated['module_keys']))));
        }

        $results = $engine->scan(
            target: $validated['target'],
            mode: $validated['mode'],
            category: $validated['category'] ?? null,
            moduleKeys: $moduleKeys,
            options: [
                'use_proxy' => (bool) ($validated['use_proxy'] ?? false),
            ]
        );

        return response()->json([
            'ok' => true,
            'meta' => [
                'mode' => $validated['mode'],
                'target' => $validated['target'],
                'count' => count($results),
            ],
            'results' => array_map(static fn ($r) => $r->toArray(), $results),
        ]);
    }

    public function modules(string $mode, ScannerEngineService $engine): JsonResponse
    {
        abort_unless(in_array($mode, ['username', 'email'], true), 404);

        return response()->json([
            'ok' => true,
            'mode' => $mode,
            'modules' => $engine->listModules($mode),
        ]);
    }
}
