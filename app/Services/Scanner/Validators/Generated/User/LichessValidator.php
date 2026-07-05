<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LichessValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'lichess';
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
        return 'Lichess';
    }

    public function siteUrl(): string
    {
        return 'https://lichess.org/@/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://lichess.org/api/user/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    protected function timeoutSeconds(): int
    {
        return 10;
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

        $profile = is_array($data['profile'] ?? null) ? $data['profile'] : [];
        $metadata = [
            'username' => trim((string) ($data['username'] ?? $target)),
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($profile['realName'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $bio = trim((string) ($profile['bio'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $links = [];
        $profileLinks = trim((string) ($profile['links'] ?? ''));
        if ($profileLinks !== '') {
            $links[] = $profileLinks;
        }

        $streamer = is_array($data['streamer'] ?? null) ? $data['streamer'] : [];
        $twitchChannel = trim((string) (($streamer['twitch']['channel'] ?? '')));
        if ($twitchChannel !== '') {
            $links[] = $twitchChannel;
        }
        $youtubeChannel = trim((string) (($streamer['youtube']['channel'] ?? '')));
        if ($youtubeChannel !== '') {
            $links[] = $youtubeChannel;
        }
        if ($links !== []) {
            $metadata['external_links'] = array_values(array_unique($links));
        }

        if (array_key_exists('verified', $data)) {
            $metadata['is_verified'] = (bool) $data['verified'];
        }
        if (array_key_exists('patron', $data)) {
            $metadata['patron'] = (bool) $data['patron'];
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
            $summary['Bio'] = $metadata['bio'];
        }
        if (($metadata['external_links'] ?? []) !== []) {
            $summary['Links'] = $metadata['external_links'];
        }
        if (array_key_exists('patron', $metadata)) {
            $summary['Patron'] = $metadata['patron'] ? 'Yes' : 'No';
        }
        if (array_key_exists('is_verified', $metadata)) {
            $summary['Verified'] = $metadata['is_verified'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }
}
