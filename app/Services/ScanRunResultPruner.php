<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ScanRunResultPruner
{
    public function pruneBefore(DateTimeInterface $cutoff, int $chunkSize): int
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The prune chunk size must be at least 1.');
        }

        $totalDeleted = 0;

        do {
            $ids = DB::table('scan_run_results')
                ->where('created_at', '<', $cutoff)
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = DB::table('scan_run_results')
                ->where('created_at', '<', $cutoff)
                ->whereIn('id', $ids)
                ->delete();

            $totalDeleted += $deleted;

            // Avoid looping forever if another cleanup process removed this batch.
            if ($deleted === 0) {
                break;
            }
        } while ($ids->count() === $chunkSize);

        return $totalDeleted;
    }
}
