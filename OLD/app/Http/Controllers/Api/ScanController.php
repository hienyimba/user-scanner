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
            if (!empty($validated['validate_proxies'])) {
                $proxyManager->validateWorking();
            }
        }

        $scan = $engine->scanWithMeta(
            target: $validated['target'],
            mode: $validated['mode'],
            category: $validated['category'] ?? null,
            moduleKeys: $validated['module_keys'] ?? null,
            options: [
                'use_proxy' => (bool) ($validated['use_proxy'] ?? false),
                'validate_proxies' => (bool) ($validated['validate_proxies'] ?? false),
                'allow_loud' => (bool) ($validated['allow_loud'] ?? false),
                'no_nsfw' => (bool) ($validated['no_nsfw'] ?? false),
                'only_found' => (bool) ($validated['only_found'] ?? false),
                'verbose' => (bool) ($validated['verbose'] ?? false),
                'delay' => (float) ($validated['delay'] ?? 0),
                'stop' => (int) ($validated['stop'] ?? 100),
            ]
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
