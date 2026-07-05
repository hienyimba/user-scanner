<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class OsuValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'osu';
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
        return 'Osu';
    }

    public function siteUrl(): string
    {
        return 'https://osu.ppy.sh/users/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://osu.ppy.sh/users/{$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200, 302 => ['Taken', ''],
            401, 403, 429 => ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $response->status() . ')'],
            default => ['Error', $this->key() . ': indeterminate username response (HTTP ' . $response->status() . ')'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $metadata = parent::buildStructuredMetadata($response, $target, $status);
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return $metadata;
        }

        if (preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $response->body(), $titleMatch) === 1) {
            $title = html_entity_decode($titleMatch[1], ENT_QUOTES | ENT_HTML5);
            $title = preg_replace('/\s*player info.*$/ui', '', $title) ?? $title;
            $title = preg_replace('/[\x{00B7}\x{2022}\|:\-]+\s*$/u', '', $title) ?? $title;
            $displayName = trim($title);
            if ($displayName !== '') {
                $metadata['display_name'] = $displayName;
                $metadata['username'] ??= $displayName;
            }
        }

        if (preg_match('/<meta\s+property="og:image"\s+content="([^"]+)"/i', $response->body(), $avatarMatch) === 1) {
            $avatarUrl = trim((string) html_entity_decode($avatarMatch[1], ENT_QUOTES | ENT_HTML5));
            if ($avatarUrl !== '') {
                $metadata['avatar_url'] = $avatarUrl;
            }
        }

        if (preg_match('/<meta\s+property="og:description"\s+content="([^"]+)"/i', $response->body(), $descriptionMatch) === 1) {
            $rankSummary = trim((string) html_entity_decode($descriptionMatch[1], ENT_QUOTES | ENT_HTML5));
            if ($rankSummary !== '') {
                $metadata['bio'] ??= $rankSummary;
                $metadata['rank_summary'] = $rankSummary;
            }
        }

        $metadata['sources'] = array_values(array_unique(array_merge(
            is_array($metadata['sources'] ?? null) ? $metadata['sources'] : [],
            ['profile_html']
        )));

        return $metadata;
    }
}
