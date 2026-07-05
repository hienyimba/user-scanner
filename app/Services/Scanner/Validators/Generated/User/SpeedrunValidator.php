<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/gaming/speedrun.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SpeedrunValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'speedrun';
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
        return 'Speedrun';
    }

    public function siteUrl(): string
    {
        return 'https://www.speedrun.com/users/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.speedrun.com/api/v1/users/{$target}";
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
        if ($response->status() === 404) {
            return ['Available', ''];
        }

        if ($response->status() === 200) {
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

        $data = $response->json('data');
        if (!is_array($data) || $data === []) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        if (!empty($data['id'])) {
            $metadata['speedrun_id'] = (string) $data['id'];
        }

        $displayName = trim((string) data_get($data, 'names.international', ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $role = trim((string) ($data['role'] ?? ''));
        if ($role !== '') {
            $metadata['role'] = $role;
        }

        $signup = trim((string) ($data['signup'] ?? ''));
        if ($signup !== '') {
            $timestamp = strtotime($signup);
            $metadata['created_at'] = $timestamp !== false
                ? gmdate('Y-m-d\TH:i:s+00:00', $timestamp)
                : $signup;
        }

        $country = trim((string) data_get($data, 'location.country.names.international', ''));
        $region = trim((string) data_get($data, 'location.region.names.international', ''));
        $location = implode(', ', array_values(array_filter([$region, $country], static fn (string $value): bool => $value !== '')));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $links = [];
        foreach (['twitch', 'youtube', 'twitter'] as $platform) {
            $uri = trim((string) data_get($data, $platform . '.uri', ''));
            if ($uri !== '') {
                $links[] = $uri;
            }
        }
        if ($links !== []) {
            $metadata['external_links'] = array_values(array_unique($links));
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

        if (is_string($metadata['speedrun_id'] ?? null) && $metadata['speedrun_id'] !== '') {
            $summary['ID'] = $metadata['speedrun_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['role'] ?? null) && $metadata['role'] !== '') {
            $summary['Role'] = $metadata['role'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Signup'] = $metadata['created_at'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (($metadata['external_links'] ?? []) !== []) {
            $summary['Links'] = $metadata['external_links'];
        }

        return $this->metadataSummary($summary);
    }
}
