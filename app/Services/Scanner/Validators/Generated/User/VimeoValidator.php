<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/creator/vimeo.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class VimeoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'vimeo';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Vimeo';
    }

    public function siteUrl(): string
    {
        return 'https://vimeo.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://vimeo.com/api/v2/{$target}/info.json";
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

        if (isset($data['id']) && is_numeric($data['id'])) {
            $metadata['vimeo_id'] = (int) $data['id'];
        } elseif (isset($data['id'])) {
            $metadata['vimeo_id'] = trim((string) $data['id']);
        }

        $displayName = trim((string) ($data['display_name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $createdOn = trim((string) ($data['created_on'] ?? ''));
        if ($createdOn !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($createdOn))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $createdOn;
            }
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $bio = trim((string) ($data['bio'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        foreach ([
            'total_videos_uploaded' => 'videos',
            'total_contacts' => 'contacts',
            'total_channels' => 'channels',
            'total_videos_liked' => 'liked',
        ] as $sourceKey => $metadataKey) {
            if (isset($data[$sourceKey]) && is_numeric($data[$sourceKey])) {
                $metadata[$metadataKey] = (int) $data[$sourceKey];
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

        if (isset($metadata['vimeo_id'])) {
            $summary['ID'] = (string) $metadata['vimeo_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (isset($metadata['videos'])) {
            $summary['Videos'] = (string) $metadata['videos'];
        }

        return $this->metadataSummary($summary);
    }
}
