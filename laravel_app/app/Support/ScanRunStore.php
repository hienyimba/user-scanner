<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class ScanRunStore
{
    private string $file;

    public function __construct()
    {
        $dir = base_path('laravel_app/storage');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create storage dir for scan runs.');
        }

        $this->file = $dir . '/scan_runs.json';

        if (!is_file($this->file)) {
            file_put_contents($this->file, json_encode(['runs' => []], JSON_PRETTY_PRINT));
        }
    }

    /** @return array<string,mixed> */
    private function load(): array
    {
        $decoded = json_decode((string) file_get_contents($this->file), true);
        return is_array($decoded) ? $decoded : ['runs' => []];
    }

    /** @param array<string,mixed> $data */
    private function save(array $data): void
    {
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }

    /** @param array<int,string> $targets */
    public function createRun(string $mode, array $targets): string
    {
        $data = $this->load();
        $id = bin2hex(random_bytes(8));

        $data['runs'][$id] = [
            'id' => $id,
            'mode' => $mode,
            'status' => 'queued',
            'targets' => $targets,
            'total' => count($targets),
            'processed' => 0,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'results' => [],
        ];

        $this->save($data);
        return $id;
    }

    /** @param array<int,array<string,mixed>> $results */
    public function appendResults(string $runId, array $results): void
    {
        $data = $this->load();
        if (!isset($data['runs'][$runId])) {
            return;
        }

        $run = &$data['runs'][$runId];
        $run['status'] = 'running';
        foreach ($results as $result) {
            $run['results'][] = $result;
            $run['processed']++;
        }

        if (($run['processed'] ?? 0) >= ($run['total'] ?? 0)) {
            $run['status'] = 'completed';
        }

        $run['updated_at'] = now()->toIso8601String();
        $this->save($data);
    }

    public function failRun(string $runId, string $message): void
    {
        $data = $this->load();
        if (!isset($data['runs'][$runId])) {
            return;
        }
        $data['runs'][$runId]['status'] = 'failed';
        $data['runs'][$runId]['updated_at'] = now()->toIso8601String();
        $data['runs'][$runId]['error'] = $message;
        $this->save($data);
    }

    /** @return array<string,mixed>|null */
    public function getRun(string $runId): ?array
    {
        $data = $this->load();
        return $data['runs'][$runId] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listRuns(?string $status = null): array
    {
        $data = $this->load();
        $runs = array_values($data['runs'] ?? []);
        usort($runs, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        if ($status === null || $status === '') {
            return $runs;
        }

        return array_values(array_filter($runs, static fn (array $run): bool => ($run['status'] ?? '') === $status));
    }

    /** @return array<int,array<string,mixed>> */
    public function filteredResults(string $runId, ?string $status = null, ?string $category = null): array
    {
        $run = $this->getRun($runId);
        if (!$run) {
            return [];
        }

        $results = $run['results'] ?? [];

        return array_values(array_filter($results, static function (array $result) use ($status, $category): bool {
            if ($status && ($result['status'] ?? '') !== $status) {
                return false;
            }
            if ($category && strcasecmp((string) ($result['category'] ?? ''), $category) !== 0) {
                return false;
            }
            return true;
        }));
    }
}
