<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

final class ProxyManagerService
{
    private int $index = 0;

    /** @var array<int, string> */
    private array $proxies = [];

    /**
     * @param string $rawList Multiline proxy list.
     */
    public function loadFromText(string $rawList): void
    {
        $lines = preg_split('/\R/', $rawList) ?: [];
        $this->proxies = Collection::make($lines)
            ->map(static fn (string $line): string => trim($line))
            ->filter(static fn (string $line): bool => $line !== '' && !str_starts_with($line, '#'))
            ->map(static fn (string $line): string => preg_match('/^[a-z0-9]+:\/\//i', $line) ? $line : 'http://' . $line)
            ->values()
            ->all();

        $this->index = 0;
    }

    public function next(): ?string
    {
        if ($this->proxies === []) {
            return null;
        }

        $proxy = $this->proxies[$this->index];
        $this->index = ($this->index + 1) % count($this->proxies);

        return $proxy;
    }

    public function count(): int
    {
        return count($this->proxies);
    }

    public function validateWorking(int $timeoutSeconds = 5): int
    {
        $working = [];
        foreach ($this->proxies as $proxy) {
            try {
                $response = Http::timeout($timeoutSeconds)
                    ->withOptions([
                        'proxy' => $proxy,
                        'verify' => false,
                    ])
                    ->get('https://www.google.com');

                if ($response->successful()) {
                    $working[] = $proxy;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $this->proxies = array_values($working);
        $this->index = 0;

        return count($this->proxies);
    }

    /** @return array<int, string> */
    public function all(): array
    {
        return $this->proxies;
    }
}
