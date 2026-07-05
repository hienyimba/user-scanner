<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/trello.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class TrelloValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'trello';
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
        return 'Trello';
    }

    public function siteUrl(): string
    {
        return 'https://trello.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://trello.com/1/Members/{$target}";
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

    if ($status === 401 || $status === 404) {
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

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $metadata = [
            'username' => trim((string) ($data['username'] ?? '')) ?: $target,
            'sources' => ['api_json'],
        ];

        if (isset($data['id']) && is_numeric($data['id'])) {
            $metadata['trello_id'] = (string) $data['id'];
        } elseif (isset($data['id'])) {
            $metadata['trello_id'] = trim((string) $data['id']);
        }

        $fullName = trim((string) ($data['fullName'] ?? ''));
        if ($fullName !== '') {
            $metadata['display_name'] = $fullName;
        }

        $bio = trim((string) ($data['bio'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $initials = trim((string) ($data['initials'] ?? ''));
        if ($initials !== '') {
            $metadata['initials'] = $initials;
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

        if (isset($metadata['trello_id'])) {
            $summary['ID'] = (string) $metadata['trello_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_string($metadata['initials'] ?? null) && $metadata['initials'] !== '') {
            $summary['Initials'] = $metadata['initials'];
        }

        return $this->metadataSummary($summary);
    }
}
