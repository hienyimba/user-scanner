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

    /**
     * @param array<int, string>|null $moduleKeys
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $mode,
        public readonly string $target,
        public readonly ?string $category = null,
        public readonly ?array $moduleKeys = null,
        public readonly array $options = [],
    ) {
    }

    public function handle(ScannerEngineService $engine, ProxyManagerService $proxyManager, ScanRunStore $store): void
    {
        try {
            if (!empty($this->options['proxy_list'])) {
                $proxyManager->loadFromText((string) $this->options['proxy_list']);
                if (!empty($this->options['validate_proxies'])) {
                    $proxyManager->validateWorking();
                }
            }

            $scan = $engine->scanWithMeta(
                target: $this->target,
                mode: $this->mode,
                category: $this->category,
                moduleKeys: $this->moduleKeys,
                options: $this->options
            );

            $store->appendResults(
                $this->runId,
                array_map(static fn ($r) => $r->toArray(), $scan['results']),
                $scan['meta']['expanded_targets'] ?? [$this->target],
            );
        } catch (\Throwable $e) {
            $store->failRun($this->runId, $e->getMessage());
        }
    }
}
