<?php

declare(strict_types=1);

namespace App\Support;

final class ScanRunPresenter
{
    /**
     * @param array<string, mixed> $run
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>
     */
    public function webSummary(array $run, array $results): array
    {
        $mode = (string) ($run['mode'] ?? 'username');

        return [
            'run_id' => $run['id'],
            'total' => count($results),
            'success' => count(array_filter($results, fn (array $result): bool => in_array(
                $result['status'] ?? '',
                $mode === 'email' ? ['Registered'] : ['Found'],
                true
            ))),
            'errors' => count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'Error')),
            'skipped' => count(array_filter($results, static fn (array $result): bool => ($result['status'] ?? '') === 'Skipped')),
            'meta' => [
                'expanded_targets' => $run['expanded_targets'] ?? [],
                'modules_scanned' => $run['validator_count'] ?? 0,
                'expected_results' => $run['expected_results'] ?? 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    public function publicApiRun(array $run, bool $showHits): array
    {
        return [
            'id' => $run['id'],
            'mode' => $run['mode'],
            'status' => $run['status'],
            'progress' => $this->progress($run),
            'processed' => $run['processed'],
            'total' => $run['total'],
            'error' => $run['error'],
            'created_at' => $run['created_at'],
            'updated_at' => $run['updated_at'],
            'completed_at' => $run['completed_at'],
            'target' => $run['targets'][0] ?? null,
            'category' => $run['options']['category'] ?? null,
            'show_hits' => $showHits,
        ];
    }

    /**
     * @param array<string, mixed> $run
     * @return array<string, mixed>
     */
    public function adminApiRun(array $run): array
    {
        return [
            'id' => $run['id'],
            'mode' => $run['mode'],
            'status' => $run['status'],
            'total' => $run['total'],
            'processed' => $run['processed'],
            'validator_count' => $run['validator_count'],
            'target_count' => $run['target_count'],
            'expected_results' => $run['expected_results'],
            'queued_jobs' => $run['queued_jobs'],
            'running_jobs' => $run['running_jobs'],
            'completed_jobs' => $run['completed_jobs'],
            'progress' => $this->progress($run),
            'created_at' => $run['created_at'] ?? null,
            'updated_at' => $run['updated_at'] ?? null,
            'completed_at' => $run['completed_at'] ?? null,
            'error' => $run['error'] ?? null,
            'options' => $run['options'] ?? [],
            'expanded_targets' => $run['expanded_targets'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $run
     */
    private function progress(array $run): float
    {
        $total = (int) ($run['total'] ?? 0);
        if ($total <= 0) {
            return 0.0;
        }

        return round((((int) ($run['processed'] ?? 0)) / $total) * 100, 2);
    }
}
