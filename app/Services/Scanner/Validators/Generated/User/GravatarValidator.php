<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/gravatar.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class GravatarValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'gravatar';
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
        return 'Gravatar';
    }

    public function siteUrl(): string
    {
        return 'https://gravatar.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://en.gravatar.com/{$target}.json";
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

    if ($status === 404 || str_contains($body, 'User not found')) {
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

        $entry = data_get($response->json(), 'entry.0');
        if (!is_array($entry)) {
            return [];
        }

        $metadata = [
            'username' => trim((string) ($entry['preferredUsername'] ?? '')) ?: $target,
            'sources' => ['api_json'],
        ];

        $gravatarId = trim((string) ($entry['hash'] ?? ''));
        if ($gravatarId !== '') {
            $metadata['gravatar_id'] = $gravatarId;
        }

        $image = trim((string) ($entry['thumbnailUrl'] ?? ''));
        if ($image !== '') {
            $metadata['avatar_url'] = $image;
        }

        $name = trim((string) data_get($entry, 'name.formatted', ''));
        if ($name === '') {
            $name = trim((string) ($entry['displayName'] ?? ''));
        }
        if ($name !== '') {
            $metadata['display_name'] = $name;
        }

        $bio = trim((string) ($entry['aboutMe'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $location = trim((string) ($entry['currentLocation'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $emails = [];
        foreach ((array) ($entry['emails'] ?? []) as $email) {
            if (!is_array($email)) {
                continue;
            }
            $value = trim((string) ($email['value'] ?? ''));
            if ($value !== '') {
                $emails[] = $value;
            }
        }
        if ($emails !== []) {
            $metadata['public_email'] = $emails[0];
            $metadata['emails'] = $emails;
        }

        $links = [];
        foreach ((array) ($entry['accounts'] ?? []) as $account) {
            if (!is_array($account)) {
                continue;
            }
            $url = trim((string) ($account['url'] ?? ''));
            if ($url !== '') {
                $links[] = $url;
            }
        }
        foreach ((array) ($entry['urls'] ?? []) as $urlEntry) {
            if (!is_array($urlEntry)) {
                continue;
            }
            $value = trim((string) ($urlEntry['value'] ?? ''));
            if ($value !== '') {
                $links[] = $value;
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

        if (isset($metadata['gravatar_id'])) {
            $summary['Gravatar ID'] = (string) $metadata['gravatar_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['public_email'] ?? null) && $metadata['public_email'] !== '') {
            $summary['Email'] = $metadata['public_email'];
        }

        return $this->metadataSummary($summary);
    }
}
