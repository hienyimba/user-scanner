<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/imgur.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ImgurValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'imgur';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Imgur';
    }

    public function siteUrl(): string
    {
        return 'https://imgur.com/user/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.imgur.com/account/v1/accounts/{$target}?client_id=546c25a59c58ad7&include=trophies%2Cmedallions";
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

        if ($status === 200 && data_get($response->json(), 'id') !== null) {
            return ['Taken', ''];
        }

        if ($status === 400 || $status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $username = trim((string) ($data['url'] ?? ($data['account_url'] ?? '')));
        if ($username === '') {
            $username = $target;
        }

        $metadata = [
            'username' => $username,
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($data['display_name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $avatarUrl = trim((string) ($data['avatar_url'] ?? data_get($data, 'avatar.url', '')));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $bio = trim((string) ($data['bio'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $websiteUrl = trim((string) ($data['website'] ?? ''));
        if ($websiteUrl !== '') {
            $metadata['website_url'] = $websiteUrl;
            $metadata['external_links'] = [$websiteUrl];
        }

        foreach ([
            'reputation' => 'reputation',
            'reputation_name' => 'reputation_name',
        ] as $sourceKey => $metadataKey) {
            $value = $data[$sourceKey] ?? null;
            if (is_numeric($value)) {
                $metadata[$metadataKey] = (int) $value;
                continue;
            }
            if (is_string($value) && trim($value) !== '') {
                $metadata[$metadataKey] = trim($value);
            }
        }

        foreach (['created_at', 'created'] as $sourceKey) {
            if (isset($data[$sourceKey]) && is_numeric($data[$sourceKey])) {
                try {
                    $metadata['created_at'] = (new \DateTimeImmutable('@' . (string) $data[$sourceKey]))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->format(DATE_ATOM);
                } catch (\Throwable) {
                    // Ignore invalid upstream timestamps.
                }
                break;
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

        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['username'] ?? null) && $metadata['username'] !== '') {
            $summary['Username'] = $metadata['username'];
        }
        if (isset($metadata['reputation'])) {
            $summary['Reputation'] = (string) $metadata['reputation'];
        }
        if (is_string($metadata['reputation_name'] ?? null) && $metadata['reputation_name'] !== '') {
            $summary['Reputation Label'] = $metadata['reputation_name'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created'] = $metadata['created_at'];
        }

        return $this->metadataSummary($summary);
    }
}
