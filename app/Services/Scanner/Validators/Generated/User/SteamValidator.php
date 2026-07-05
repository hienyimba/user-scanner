<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/gaming/steam.py
// parity-class: generated

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SteamValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'steam';
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
        return 'Steam';
    }

    public function siteUrl(): string
    {
        return 'https://steamcommunity.com/id/{user}/';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://steamcommunity.com/id/{$target}/";
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
        $body = $response->body();
        $finalUrl = (string) ($response->effectiveUri() ?? '');

        if ($status === 200 && !str_contains($body, 'Error</title>')) {
            return ['Taken', ''];
        }

        if (str_contains($body, 'Error</title>')) {
            return ['Available', ''];
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

        $body = $response->body();
        $metadata = [
            'username' => $target,
            'sources' => ['profile_html'],
        ];

        if (preg_match('/"steamid"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $body, $matches) === 1) {
            $metadata['steam_id'] = $matches[1];
        }

        if (preg_match('/"personaname"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $body, $matches) === 1) {
            $persona = html_entity_decode(str_replace(['\/', '\"'], ['/', '"'], $matches[1]), ENT_QUOTES | ENT_HTML5);
            if (trim($persona) !== '') {
                $metadata['display_name'] = trim($persona);
            }
        }

        if (preg_match('/class="header_real_name[^"]*">\s*<bdi>([^<]+)<\/bdi>/', $body, $matches) === 1) {
            $realName = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5);
            if ($realName !== '') {
                $metadata['real_name'] = $realName;
            }
        }

        if (preg_match('/class="header_location"[^>]*>\s*(?:<img[^>]*>\s*)?([^<\r\n]+)/', $body, $matches) === 1) {
            $location = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5);
            if ($location !== '') {
                $metadata['location'] = $location;
            }
        }

        if (preg_match('/class="playerAvatar profile_header_size.*?<picture>.*?<img srcset="([^"]+)"/s', $body, $matches) === 1) {
            $metadata['avatar_url'] = trim($matches[1]);
        } elseif (preg_match('/https?:\/\/avatars\.(?:fastly\.)?steamstatic\.com\/[a-f0-9]+_full\.jpg/', $body, $matches) === 1) {
            $metadata['avatar_url'] = trim($matches[0]);
        }

        if (preg_match('/"summary"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $body, $matches) === 1) {
            $rawSummary = str_replace(['\/', '\n', '\r', '\"'], ['/', "\n", "\r", '"'], $matches[1]);
            $cleanSummary = trim(html_entity_decode(strip_tags($rawSummary), ENT_QUOTES | ENT_HTML5));
            if ($cleanSummary !== '') {
                $metadata['bio'] = $cleanSummary;
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

        if (isset($metadata['steam_id'])) {
            $summary['Steam ID'] = (string) $metadata['steam_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Persona'] = $metadata['display_name'];
        }
        if (is_string($metadata['real_name'] ?? null) && $metadata['real_name'] !== '') {
            $summary['Real Name'] = $metadata['real_name'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Summary'] = $metadata['bio'];
        }

        return $this->metadataSummary($summary);
    }
}
