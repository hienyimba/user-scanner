<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ChessComValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'chess_com';
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
        return 'ChessCom';
    }

    public function siteUrl(): string
    {
        return 'https://www.chess.com/member/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.chess.com/pub/player/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 200) {
            return ['Taken', ''];
        }

        if ($response->status() === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected status code: ' . $response->status()];
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
            'username' => trim((string) ($data['username'] ?? $target)),
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($data['name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        foreach (['title', 'status', 'league'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value !== '') {
                $metadata[$field] = $value;
            }
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        if (isset($data['followers']) && is_numeric($data['followers'])) {
            $metadata['followers'] = (int) $data['followers'];
        }

        $avatarUrl = trim((string) ($data['avatar'] ?? ''));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $twitchUrl = trim((string) ($data['twitch_url'] ?? ''));
        if ($twitchUrl !== '') {
            $metadata['external_links'] = [$twitchUrl];
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
        if (is_string($metadata['title'] ?? null) && $metadata['title'] !== '') {
            $summary['Title'] = $metadata['title'];
        }
        if (is_string($metadata['status'] ?? null) && $metadata['status'] !== '') {
            $summary['Status'] = $metadata['status'];
        }
        if (is_string($metadata['league'] ?? null) && $metadata['league'] !== '') {
            $summary['League'] = $metadata['league'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_int($metadata['followers'] ?? null)) {
            $summary['Followers'] = $metadata['followers'];
        }
        if (is_string($metadata['avatar_url'] ?? null) && $metadata['avatar_url'] !== '') {
            $summary['Avatar'] = $metadata['avatar_url'];
        }
        if (($metadata['external_links'] ?? []) !== []) {
            $summary['Links'] = $metadata['external_links'];
        }

        return $this->metadataSummary($summary);
    }
}
