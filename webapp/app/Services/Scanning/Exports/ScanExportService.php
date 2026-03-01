<?php

declare(strict_types=1);

namespace App\Services\Scanning\Exports;

use App\Models\ScanBatch;

class ScanExportService
{
    public function toJson(ScanBatch $scan): string
    {
        $scan->loadMissing('results');

        return (string) json_encode([
            'id' => $scan->id,
            'type' => $scan->type->value ?? $scan->type,
            'target' => $scan->target,
            'status' => $scan->status->value ?? $scan->status,
            'created_at' => (string) $scan->created_at,
            'results' => $scan->results->map(fn ($result): array => [
                'connector_key' => $result->connector_key,
                'category' => $result->category,
                'site_name' => $result->site_name,
                'status' => $result->status->value ?? $result->status,
                'reason' => $result->reason,
                'checked_url' => $result->checked_url,
                'confidence' => $result->confidence,
                'response_metadata' => $result->response_metadata,
                'created_at' => (string) $result->created_at,
            ])->values()->all(),
        ], JSON_PRETTY_PRINT);
    }

    public function toCsv(ScanBatch $scan): string
    {
        $scan->loadMissing('results');

        $rows = [[
            'scan_id',
            'type',
            'target',
            'connector_key',
            'category',
            'site_name',
            'status',
            'reason',
            'checked_url',
            'confidence',
            'created_at',
        ]];

        foreach ($scan->results as $result) {
            $rows[] = [
                (string) $scan->id,
                $scan->type->value ?? (string) $scan->type,
                $scan->target,
                $result->connector_key,
                $result->category ?? '',
                $result->site_name ?? '',
                $result->status->value ?? (string) $result->status,
                $result->reason ?? '',
                $result->checked_url ?? '',
                $result->confidence ?? 'mid',
                (string) $result->created_at,
            ];
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}
