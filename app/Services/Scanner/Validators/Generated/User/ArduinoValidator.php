<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/arduino.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ArduinoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'arduino';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Arduino';
    }

    public function siteUrl(): string
    {
        return 'https://forum.arduino.cc/u/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://forum.arduino.cc/u/{$target}.json";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeaders(): array
    {
        return [];
    }

    protected function requestQuery(string $target): array
    {
        return [];
    }


    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            return ['Available', ''];
        }

        $user = $response->json('user');
        if ($status === 200 && is_array($user) && ($user['username'] ?? null) !== null) {
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

        $user = $response->json('user');
        if (!is_array($user)) {
            return [];
        }

        $metadata = [];

        $name = trim((string) ($user['name'] ?? ''));
        if ($name !== '') {
            $metadata['display_name'] = $name;
        }

        $username = trim((string) ($user['username'] ?? ''));
        if ($username !== '') {
            $metadata['username'] = $username;
        }

        $avatarTemplate = trim((string) ($user['avatar_template'] ?? ''));
        if ($avatarTemplate !== '') {
            $metadata['avatar_url'] = str_replace('{size}', '512', $avatarTemplate);
        }

        if (array_key_exists('profile_hidden', $user)) {
            $metadata['is_private'] = (bool) $user['profile_hidden'];
        }

        if ($metadata !== []) {
            $metadata['sources'] = ['api_json'];
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
        if (is_string($metadata['avatar_url'] ?? null) && $metadata['avatar_url'] !== '') {
            $summary['Avatar'] = $metadata['avatar_url'];
        }
        if (array_key_exists('is_private', $metadata)) {
            $summary['Profile Hidden'] = $metadata['is_private'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }
}
