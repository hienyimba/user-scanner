<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ModuleSkipService
{
    public function setSkip(string $mode, string $moduleKey, string $duration): void
    {
        $mode = strtolower(trim($mode));
        $moduleKey = trim($moduleKey);
        $duration = strtolower(trim($duration));

        $expiresAt = match ($duration) {
            '6h' => now()->addHours(6),
            'permanent' => null,
            default => throw new \InvalidArgumentException('Unsupported skip duration.'),
        };

        DB::table('scanner_module_skip_flags')->updateOrInsert(
            [
                'mode' => $mode,
                'module_key' => $moduleKey,
            ],
            [
                'duration' => $duration,
                'reason' => $this->reasonFor($duration),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function clearSkip(string $mode, string $moduleKey): void
    {
        if (!$this->skipTableAvailable()) {
            return;
        }

        DB::table('scanner_module_skip_flags')
            ->where('mode', strtolower(trim($mode)))
            ->where('module_key', trim($moduleKey))
            ->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeSkipFor(string $mode, string $moduleKey): ?array
    {
        if (!$this->skipTableAvailable()) {
            return null;
        }

        $row = DB::table('scanner_module_skip_flags')
            ->where('mode', strtolower(trim($mode)))
            ->where('module_key', trim($moduleKey))
            ->first();

        if ($row === null) {
            return null;
        }

        $expiresAt = $row->expires_at !== null ? Carbon::parse((string) $row->expires_at) : null;
        if ($expiresAt !== null && $expiresAt->lte(now())) {
            $this->clearSkip((string) $row->mode, (string) $row->module_key);

            return null;
        }

        return [
            'mode' => (string) $row->mode,
            'module_key' => (string) $row->module_key,
            'duration' => (string) $row->duration,
            'reason' => (string) $row->reason,
            'expires_at' => $expiresAt?->toIso8601String(),
            'label' => $this->labelFor((string) $row->duration, $expiresAt),
        ];
    }

    /**
     * @param array<int, string> $moduleKeys
     * @return array<string, array<string, mixed>>
     */
    public function activeSkipsForMany(string $mode, array $moduleKeys): array
    {
        if (!$this->skipTableAvailable()) {
            return [];
        }

        $keys = array_values(array_unique(array_filter(array_map('trim', $moduleKeys), static fn (string $key): bool => $key !== '')));
        if ($keys === []) {
            return [];
        }

        $rows = DB::table('scanner_module_skip_flags')
            ->where('mode', strtolower(trim($mode)))
            ->whereIn('module_key', $keys)
            ->get();

        $active = [];
        foreach ($rows as $row) {
            $expiresAt = $row->expires_at !== null ? Carbon::parse((string) $row->expires_at) : null;
            if ($expiresAt !== null && $expiresAt->lte(now())) {
                $this->clearSkip((string) $row->mode, (string) $row->module_key);
                continue;
            }

            $active[(string) $row->module_key] = [
                'mode' => (string) $row->mode,
                'module_key' => (string) $row->module_key,
                'duration' => (string) $row->duration,
                'reason' => (string) $row->reason,
                'expires_at' => $expiresAt?->toIso8601String(),
                'label' => $this->labelFor((string) $row->duration, $expiresAt),
            ];
        }

        return $active;
    }

    private function reasonFor(string $duration): string
    {
        return match ($duration) {
            '6h' => 'Skipped from ops dashboard for 6 hours',
            'permanent' => 'Skipped from ops dashboard until manually cleared',
            default => 'Skipped from ops dashboard',
        };
    }

    private function labelFor(string $duration, ?Carbon $expiresAt): string
    {
        return match ($duration) {
            '6h' => $expiresAt !== null
                ? 'Skipped until ' . $expiresAt->format('M j, H:i')
                : 'Skipped for 6 hours',
            'permanent' => 'Skipped permanently',
            default => 'Skipped',
        };
    }

    private function skipTableAvailable(): bool
    {
        try {
            return Schema::hasTable('scanner_module_skip_flags');
        } catch (Throwable) {
            return false;
        }
    }
}
