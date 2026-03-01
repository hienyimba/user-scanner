<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Enums\ResultStatus;
use App\Enums\ScanStatus;
use App\Models\ScanBatch;
use Throwable;

class ScanOrchestratorService
{
    public function __construct(private readonly ConnectorRegistry $registry)
    {
    }

    public function run(ScanBatch $batch): void
    {
        $options = $batch->options ?? [];
        $connectors = $this->registry->forType($batch->type, $options);

        $batch->update([
            'status' => ScanStatus::Running,
            'started_at' => now(),
            'total_items' => $connectors->count(),
        ]);

        $retryLimit = max(1, (int) ($options['retry_limit'] ?? config('scanner.queue.default_retry_limit', 3)));
        $cancelPollEvery = max(1, (int) config('scanner.performance.cancel_poll_every', 5));

        $processed = 0;
        $errorCount = 0;

        foreach ($connectors as $index => $connector) {
            if ($index % $cancelPollEvery === 0) {
                $batch->refresh();
                if ($batch->status === ScanStatus::Cancelled) {
                    break;
                }
            }

            $lastError = null;

            for ($attempt = 1; $attempt <= $retryLimit; $attempt++) {
                try {
                    $result = $connector->scan($batch->target, $options + ['attempt' => $attempt]);
                    $batch->results()->create($result->toArray());
                    $processed++;

                    if (($result->status->value ?? (string) $result->status) === ResultStatus::Error->value) {
                        $errorCount++;
                    }

                    $lastError = null;
                    break;
                } catch (Throwable $exception) {
                    $lastError = $exception;
                }
            }

            if ($lastError !== null) {
                $batch->results()->create([
                    'connector_key' => $connector->key(),
                    'category' => $connector->category(),
                    'site_name' => ucfirst($connector->key()),
                    'status' => ResultStatus::Error,
                    'reason' => sprintf('Connector failed after retries: %s', $lastError->getMessage()),
                    'checked_url' => null,
                    'confidence' => 'low',
                    'response_metadata' => [],
                ]);
                $processed++;
                $errorCount++;
            }

            if ($processed % 10 === 0) {
                $batch->update([
                    'processed_items' => $processed,
                    'error_count' => $errorCount,
                ]);
            }
        }

        $batch->refresh();
        $batch->update([
            'status' => $batch->status === ScanStatus::Cancelled ? ScanStatus::Cancelled : ScanStatus::Completed,
            'processed_items' => $processed,
            'error_count' => $errorCount,
            'finished_at' => now(),
        ]);
    }
}
