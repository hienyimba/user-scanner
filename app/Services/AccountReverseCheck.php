<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AccountReverseCheck
{
    protected const CREATE_ENDPOINT = '/v1/scan';
    protected const FINAL_ENDPOINT = '/v1/scan/{runId}/final';

    protected ?string $scannerApiBaseUrl = 'https://webvet.downloadcenter.app/api';
    protected array $defaultHeaders = [];
    protected array $defaultCreatePayload = [
        'show_hits' => true,
        'use_proxy' => true,
    ];
    protected int $timeoutSeconds = 15;
    protected int $pollIntervalMs = 1500;
    protected int $maxPollAttempts = 40;

    public function __construct()
    {
        $this->scannerApiBaseUrl = trim((string) $this->scannerApiBaseUrl) !== ''
            ? rtrim(trim((string) $this->scannerApiBaseUrl), '/')
            : null;
        $this->timeoutSeconds = max(1, $this->timeoutSeconds);
        $this->pollIntervalMs = max(100, $this->pollIntervalMs);
        $this->maxPollAttempts = max(1, $this->maxPollAttempts);
    }

    public function fetch(string $query, ?string $type): array
    {
        $query = trim($query);
        $type = preg_replace('/^(reverse_|monitor_)/', '', (string) $type);

        if ($query === '') {
            throw new InvalidArgumentException('Query cannot be empty.');
        }

        if (!in_array($type, ['phone', 'email', 'username'], true)) {
            throw new InvalidArgumentException('Invalid query type.');
        }

        if ($type === 'phone') {
            return $this->errorResponse($query, $type, 'Phone lookups are not supported by the scanner API.');
        }

        if (empty(trim((string)$this->scannerApiBaseUrl))) {
            return $this->errorResponse($query, $type, 'Scanner API base URL is not configured.');
        }

        try {
            $run = $this->startRun($query, $type);
            $runId = $run['run_id'] ?? null;

            if (!$runId) {
                return $this->errorResponse($query, $type, $run['error'] ?? 'Scanner API did not return a run id.', $run);
            }

            $final = $this->awaitFinalResults((string) $runId);

            return $this->normalize(array_merge($final, ['create_response' => $run]), $query, $type);
        } catch (Throwable $e) {
            return $this->errorResponse($query, $type, $e->getMessage());
        }
    }

    protected function startRun(string $query, string $type): array
    {
        $payload = array_merge($this->defaultCreatePayload, [
            'mode' => $type,
            'target' => $query,
        ]);

        unset($payload['runId']); // Clean up any unintended overrides

        $response = $this->httpClient()->post($this->buildUrl(self::CREATE_ENDPOINT), $payload);

        return $response->json() ?? [];
    }

    protected function awaitFinalResults(string $runId): array
    {
        $url = $this->buildUrl(str_replace('{runId}', rawurlencode($runId), self::FINAL_ENDPOINT));
        $lastPayload = [];

        for ($attempt = 0; $attempt < $this->maxPollAttempts; $attempt++) {
            $response = $this->httpClient()->get($url);
            $payload = $response->json() ?? [];
            
            if (!empty($payload)) {
                $lastPayload = $payload;
            }

            if ($response->successful() && !empty($payload['ready'])) {
                return $payload;
            }

            if (in_array($response->status(), [404, 409], true)) {
                return array_merge($payload, ['results' => []]);
            }

            if ($response->status() !== 202) {
                return array_merge($payload, [
                    'ok' => false,
                    'error' => $payload['error'] ?? "Unexpected scanner API status: {$response->status()}",
                    'results' => $payload['results'] ?? [],
                ]);
            }

            if ($attempt < $this->maxPollAttempts - 1) {
                usleep($this->pollIntervalMs * 1000);
            }
        }

        return array_merge($lastPayload, [
            'ok' => false,
            'ready' => false,
            'error' => $lastPayload['error'] ?? 'Timed out waiting for scanner results.',
            'results' => $lastPayload['results'] ?? [],
        ]);
    }

    protected function normalize(array $raw, string $query, string $type): array
    {
        $results = collect($raw['results'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => $this->normalizeResultRow($row, $query, $type))
            ->values()
            ->all();

        return [
            'source_id' => 'reverse_osint_check_v1',
            'query' => $query,
            'raw' => array_merge($raw, [
                'query' => $query,
                'type' => $type,
                'count' => count($results),
                'results' => $results,
                'error' => trim((string) ($raw['error'] ?? '')),
                'ok' => (bool) ($raw['ok'] ?? false),
                'ready' => (bool) ($raw['ready'] ?? false),
            ]),
        ];
    }

    protected function normalizeResultRow(array $row, string $query, string $type): array
    {
        $url = $row['profile_url'] ?? $row['url'] ?? '';
        $domain = parse_url($url, PHP_URL_HOST) ?: ($url ?: 'example.com');
        $platform = trim((string) ($row['site_name'] ?? $row['platform'] ?? $domain ?: 'Unknown Platform'));
        
        $metadata = $this->flattenMetadataEntries($this->cleanMetadata($row));

        if ($type === 'email' && filter_var($query, FILTER_VALIDATE_EMAIL)) {
            array_unshift($metadata, ['label' => 'Connected Email', 'value' => strtolower($query), 'kind' => 'email']);
        }

        $status = strtolower(trim((string) ($row['normalized_status'] ?? $row['status'] ?? '')));

        return [
            'platform' => $platform,
            'category' => (string) ($row['category'] ?? ''),
            'url' => $url,
            'exists' => in_array($status, ['found', 'registered'], true),
            'icon_url' => $domain !== 'example.com' ? "https://www.google.com/s2/favicons?domain=" . rawurlencode($domain) . "&sz=128" : '',
            'domain' => $domain,
            'metadata' => $metadata,
        ];
    }

    protected function cleanMetadata(array $row): array
    {
        $metadata = Arr::except((array) ($row['metadata'] ?? []), [
            'platform', 'status_detail', 'observed_metadata_level', 'evidence', 'sources'
        ]);

        if (!empty($row['extra'])) {
            $notes = (array) ($metadata['notes'] ?? []);
            foreach (preg_split('/\r\n|\r|\n/', trim($row['extra'])) as $line) {
                if (str_contains($line, ':')) {
                    [$label, $value] = array_map('trim', explode(':', $line, 2));
                    $key = Str::snake($label);
                    if ($this->shouldAppendExtraMetadata($metadata, $key, $value)) {
                        $metadata[$key] ??= $value;
                    }
                } elseif (trim($line)) {
                    $notes[] = trim($line);
                }
            }
            if (!empty($notes)) {
                $metadata['notes'] = array_values(array_filter($notes));
            }
        }

        return $metadata;
    }

    protected function shouldAppendExtraMetadata(array $metadata, string $key, string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $aliasMap = [
            'name' => 'display_name',
            'full_name' => 'display_name',
        ];

        $canonicalKey = $aliasMap[$key] ?? $key;
        $existingValue = $metadata[$canonicalKey] ?? null;
        if (is_scalar($existingValue) && trim((string) $existingValue) === $value) {
            return false;
        }

        foreach ($metadata as $existing) {
            if (is_scalar($existing) && trim((string) $existing) === $value) {
                return false;
            }
        }

        return true;
    }

    protected function flattenMetadataEntries(array $metadata, string $prefix = ''): array
    {
        return collect($metadata)->flatMap(function ($value, $key) use ($prefix) {
            $label = trim($prefix . ' ' . Str::headline((string) $key));

            if (is_array($value)) {
                if (empty($value)) return [];
                if (Arr::isAssoc($value)) return $this->flattenMetadataEntries($value, $label);
                
                $hasNested = collect($value)->contains(fn ($i) => is_array($i) || is_object($i));
                return [[
                    'label' => $label, 
                    'value' => $hasNested ? json_encode($value) : implode(', ', array_filter($value)), 
                    'kind' => 'text'
                ]];
            }

            $value = is_bool($value) ? ($value ? 'Yes' : 'No') : trim((string) $value);
            if ($value === '') return [];

            return [[
                'label' => $label,
                'value' => $value,
                'kind' => match(true) {
                    (bool) filter_var($value, FILTER_VALIDATE_EMAIL) => 'email',
                    (bool) filter_var($value, FILTER_VALIDATE_URL) => Str::contains(strtolower($label), ['photo', 'image', 'avatar']) ? 'image' : 'url',
                    in_array(strtolower($value), ['yes', 'no', 'true', 'false'], true) => 'boolean',
                    default => 'text',
                },
            ]];
        })->values()->all();
    }

    protected function errorResponse(string $query, string $type, string $message, array $createResponse = []): array
    {
        return [
            'source_id' => 'reverse_osint_check_v1',
            'query' => $query,
            'raw' => [
                'ok' => false,
                'ready' => false,
                'query' => $query,
                'type' => $type,
                'count' => 0,
                'error' => $message,
                'results' => [],
                'create_response' => $createResponse,
            ],
        ];
    }

    protected function httpClient()
    {
        return Http::acceptJson()
            ->timeout($this->timeoutSeconds)
            ->withHeaders(array_filter($this->defaultHeaders, 'is_scalar'));
    }

    protected function buildUrl(string $path): string
    {
        return rtrim($this->scannerApiBaseUrl, '/') . '/' . ltrim($path, '/');
    }
}
