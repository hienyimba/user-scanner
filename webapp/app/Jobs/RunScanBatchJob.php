<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ScanBatch;
use App\Services\Scanning\ScanOrchestratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunScanBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $batchId)
    {
    }

    public function handle(ScanOrchestratorService $orchestrator): void
    {
        $batch = ScanBatch::findOrFail($this->batchId);
        $orchestrator->run($batch);
    }
}
