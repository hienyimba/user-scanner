<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/gaming/kick.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class KickValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'kick';
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
        return 'Kick';
    }

    public function siteUrl(): string
    {
        return 'https://kick.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://kick.com/api/v2/channels/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
        ];
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

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status !== 200) {
            return ['Error', 'Unexpected status: ' . $status];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return ['Error', 'Unexpected response body'];
        }

        return ['Taken', ''];
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

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        if (isset($data['id']) && is_numeric($data['id'])) {
            $metadata['kick_id'] = (int) $data['id'];
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug !== '') {
            $metadata['slug'] = $slug;
        }

        if (array_key_exists('is_banned', $data)) {
            $metadata['is_banned'] = (bool) $data['is_banned'];
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

        if (is_int($metadata['kick_id'] ?? null)) {
            $summary['ID'] = $metadata['kick_id'];
        }
        if (is_string($metadata['slug'] ?? null) && $metadata['slug'] !== '') {
            $summary['Slug'] = $metadata['slug'];
        }
        if (array_key_exists('is_banned', $metadata)) {
            $summary['Is Banned'] = $metadata['is_banned'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }
}
