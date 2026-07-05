<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/calendly.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class CalendlyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'calendly';
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
        return 'Calendly';
    }

    public function siteUrl(): string
    {
        return 'https://calendly.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://calendly.com/api/booking/profiles/{$target}";
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

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $metadata['display_name'] = $name;
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($description !== '') {
            $metadata['bio'] = $description;
        }

        $avatar = trim((string) ($data['avatar_url'] ?? ''));
        if ($avatar !== '') {
            $metadata['avatar_url'] = $avatar;
        }

        $organization = trim((string) ($data['organization_uuid'] ?? ''));
        if ($organization !== '') {
            $metadata['organization_uuid'] = $organization;
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
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Description'] = $metadata['bio'];
        }
        if (is_string($metadata['organization_uuid'] ?? null) && $metadata['organization_uuid'] !== '') {
            $summary['Organization UUID'] = $metadata['organization_uuid'];
        }

        return $this->metadataSummary($summary);
    }
}
