<?php

declare(strict_types=1);

namespace App\Services\Scanner;

final class MetadataTargetResolver
{
    /**
     * @param array<int, string> $targets
     * @return array{resolved: array<int, string>, unresolved: array<int, string>, labels_by_resolved: array<string, string>}
     */
    public function resolveMany(string $mode, array $targets): array
    {
        $resolved = [];
        $unresolved = [];
        $labelsByResolved = [];

        foreach ($targets as $target) {
            $label = trim((string) $target);
            $result = $this->resolveOne($mode, $label);
            if ($result === null) {
                $unresolved[] = $label;
                continue;
            }

            $resolved[] = $result;
            $labelsByResolved[$result] ??= $label;
        }

        return [
            'resolved' => array_values(array_unique($resolved)),
            'unresolved' => array_values(array_unique($unresolved)),
            'labels_by_resolved' => $labelsByResolved,
        ];
    }

    public function resolveOne(string $mode, string $target): ?string
    {
        $target = trim($target);
        if ($target === '') {
            return null;
        }

        if ($mode !== 'email') {
            return $target;
        }

        if (filter_var($target, FILTER_VALIDATE_EMAIL) !== false) {
            return strtolower($target);
        }

        $aliases = config('scanner_private_targets.email', []);
        $resolved = $aliases[$target] ?? null;
        if (!is_string($resolved)) {
            return null;
        }

        $resolved = trim($resolved);

        return filter_var($resolved, FILTER_VALIDATE_EMAIL) !== false
            ? strtolower($resolved)
            : null;
    }
}
