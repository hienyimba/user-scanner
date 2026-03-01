<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ScanRunStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RunScanJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $runId,
        public readonly string $mode,
        public readonly string $target,
        public readonly ?string $category = null,
        public readonly ?array $moduleKeys = null,
        public readonly bool $useProxy = false,
        public readonly ?string $proxyList = null,
    ) {
    }

    public function handle(ScannerEngineService $engine, ProxyManagerService $proxyManager, ScanRunStore $store): void
    {
        try {
            if (!empty($this->proxyList)) {
                $proxyManager->loadFromText($this->proxyList);
            }

            $results = $engine->scan(
                target: $this->target,
                mode: $this->mode,
                category: $this->category,
                moduleKeys: $this->moduleKeys,
                options: ['use_proxy' => $this->useProxy]
            );

            $store->appendResults($this->runId, array_map(static fn ($r) => $r->toArray(), $results));
        } catch (\Throwable $e) {
            $store->failRun($this->runId, $e->getMessage());
        }
    }
}
