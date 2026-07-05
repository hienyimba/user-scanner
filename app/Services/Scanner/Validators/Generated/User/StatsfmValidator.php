<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/statsfm.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class StatsfmValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'statsfm';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Statsfm';
    }

    public function siteUrl(): string
    {
        return 'https://stats.fm/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.stats.fm/api/v1/users/{$target}";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
{
    $status = $response->status();
    $body = $response->body();

    if ($status === 404) {
        return ['Available', ''];
    }

    if ($status === 200) {
        return ['Taken', ''];
    }

    return ['Error', 'Unexpected response body'];
}

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $item = data_get($response->json(), 'item');
        if (!is_array($item) || !isset($item['id'])) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $metadata['statsfm_id'] = is_numeric($item['id']) ? (int) $item['id'] : (string) $item['id'];

        $displayName = trim((string) ($item['displayName'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $createdAt = trim((string) ($item['createdAt'] ?? ''));
        if ($createdAt !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($createdAt))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $createdAt;
            }
        }

        foreach (['isPlus' => 'is_plus', 'isPro' => 'is_pro', 'quarantined' => 'quarantined'] as $sourceKey => $metadataKey) {
            if (array_key_exists($sourceKey, $item)) {
                $metadata[$metadataKey] = (bool) $item[$sourceKey];
            }
        }

        return $metadata;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];

        if (isset($metadata['statsfm_id'])) {
            $summary['ID'] = (string) $metadata['statsfm_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created'] = $metadata['created_at'];
        }
        if (array_key_exists('is_plus', $metadata)) {
            $summary['Plus'] = $metadata['is_plus'] ? 'Yes' : 'No';
        }
        if (array_key_exists('is_pro', $metadata)) {
            $summary['Pro'] = $metadata['is_pro'] ? 'Yes' : 'No';
        }
        if (array_key_exists('quarantined', $metadata)) {
            $summary['Quarantined'] = $metadata['quarantined'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }
}
