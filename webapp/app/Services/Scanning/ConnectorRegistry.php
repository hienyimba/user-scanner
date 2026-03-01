<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Enums\ScanType;
use App\Services\Scanning\Contracts\ConnectorInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ConnectorRegistry
{
    /**
     * @return Collection<int, ConnectorInterface>
     */
    public function all(): Collection
    {
        $cacheKey = 'scanner:connector:classes:v1';
        $ttl = max(30, (int) config('scanner.connector_cache_ttl_seconds', 300));

        /** @var list<class-string<ConnectorInterface>> $connectorClasses */
        $connectorClasses = Cache::remember($cacheKey, $ttl, function (): array {
            $configured = config('scanner.connectors', []);

            return is_array($configured) ? array_values($configured) : [];
        });

        return collect($connectorClasses)
            ->map(static fn (string $class): ConnectorInterface => app($class));
    }

    /**
     * @param array<string, mixed> $options
     * @return Collection<int, ConnectorInterface>
     */
    public function forType(ScanType $type, array $options = []): Collection
    {
        $module = isset($options['module']) ? (string) $options['module'] : null;
        $category = isset($options['category']) ? (string) $options['category'] : null;

        return $this->all()->filter(
            static fn (ConnectorInterface $connector): bool => $connector->supports($type)
        )->filter(
            static fn (ConnectorInterface $connector): bool => $module === null || $connector->key() === $module
        )->filter(
            static fn (ConnectorInterface $connector): bool => $category === null || $connector->category() === $category
        )->values();
    }
}
