<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/gaming/warframemarket.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class WarframemarketValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'warframemarket';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Warframemarket';
    }

    public function siteUrl(): string
    {
        return 'https://warframe.market/profile/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.warframe.market/v2/user/{$target}";
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

        $data = data_get($response->json(), 'data');
        if (!is_array($data) || !isset($data['id'])) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $metadata['warframemarket_id'] = is_numeric($data['id']) ? (int) $data['id'] : (string) $data['id'];

        $role = trim((string) ($data['role'] ?? ''));
        if ($role !== '') {
            $metadata['account_type'] = $role;
        }

        $ingameName = trim((string) ($data['ingameName'] ?? ''));
        if ($ingameName !== '') {
            $metadata['display_name'] = $ingameName;
            $metadata['ingame_name'] = $ingameName;
        }

        foreach ([
            'reputation' => 'reputation',
            'masteryRank' => 'mastery_rank',
        ] as $sourceKey => $metadataKey) {
            if (isset($data[$sourceKey]) && is_numeric($data[$sourceKey])) {
                $metadata[$metadataKey] = (int) $data[$sourceKey];
            }
        }

        $statusValue = trim((string) ($data['status'] ?? ''));
        if ($statusValue !== '') {
            $metadata['status'] = $statusValue;
        }

        $lastSeen = trim((string) ($data['lastSeen'] ?? ''));
        if ($lastSeen !== '') {
            try {
                $metadata['last_active_at'] = (new \DateTimeImmutable($lastSeen))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['last_active_at'] = $lastSeen;
            }
        }

        $platform = trim((string) ($data['platform'] ?? ''));
        if ($platform !== '') {
            $metadata['market_platform'] = $platform;
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

        if (isset($metadata['warframemarket_id'])) {
            $summary['ID'] = (string) $metadata['warframemarket_id'];
        }
        if (is_string($metadata['ingame_name'] ?? null) && $metadata['ingame_name'] !== '') {
            $summary['Ingame Name'] = $metadata['ingame_name'];
        }
        if (is_string($metadata['account_type'] ?? null) && $metadata['account_type'] !== '') {
            $summary['Role'] = $metadata['account_type'];
        }
        if (isset($metadata['reputation'])) {
            $summary['Reputation'] = (string) $metadata['reputation'];
        }
        if (isset($metadata['mastery_rank'])) {
            $summary['Mastery Rank'] = (string) $metadata['mastery_rank'];
        }

        return $this->metadataSummary($summary);
    }
}
