<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LinktreeValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'linktree'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Linktree'; }
    public function siteUrl(): string { return 'https://linktr.ee/{user}'; }
    protected function requestUrl(string $target): string { return "https://linktr.ee/{$target}"; }
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
        ];
    }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $html = $response->body();
        $metadata = [
            'username' => $target,
            'sources' => ['profile_html'],
        ];

        $externalLinks = [];
        $nextData = $this->extractNextData($html);
        if (is_array($nextData)) {
            $pageProps = $nextData['props']['pageProps'] ?? null;
            $account = is_array($pageProps['account'] ?? null) ? $pageProps['account'] : [];

            $displayName = trim((string) (($pageProps['pageTitle'] ?? null) ?: ($account['pageTitle'] ?? null) ?: ''));
            if ($displayName !== '') {
                $metadata['display_name'] = $displayName;
            }

            $bio = trim((string) (($pageProps['description'] ?? null) ?: ($account['description'] ?? null) ?: ''));
            if ($bio !== '') {
                $metadata['bio'] = $bio;
            }

            $avatar = trim((string) (($account['profilePictureUrl'] ?? null) ?: ($pageProps['customAvatar'] ?? null) ?: ''));
            if ($avatar !== '') {
                $metadata['avatar_url'] = $avatar;
            }

            if (array_key_exists('isProfileVerified', $pageProps) && is_bool($pageProps['isProfileVerified'])) {
                $metadata['is_verified'] = $pageProps['isProfileVerified'];
            }

            foreach ((array) ($pageProps['links'] ?? []) as $link) {
                if (!is_array($link)) {
                    continue;
                }

                $url = trim((string) ($link['url'] ?? ''));
                if ($url !== '') {
                    $externalLinks[] = $url;
                }
            }

            foreach ((array) ($pageProps['socialLinks'] ?? []) as $link) {
                if (!is_array($link)) {
                    continue;
                }

                $url = trim((string) ($link['url'] ?? ''));
                if ($url !== '') {
                    $externalLinks[] = $url;
                }
            }
        }

        if (!isset($metadata['display_name']) && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) === 1) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            $title = preg_replace('/\s*\|\s*Linktree$/i', '', $title) ?? $title;
            if ($title !== '') {
                $metadata['display_name'] = $title;
            }
        }

        if (!isset($metadata['bio']) && preg_match('/<meta[^>]*property="og:description"[^>]*content="([^"]+)"/i', $html, $matches) === 1) {
            $bio = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($bio !== '') {
                $metadata['bio'] = $bio;
            }
        }

        if (!isset($metadata['avatar_url']) && preg_match('/<meta[^>]*property="og:image"[^>]*content="([^"]+)"/i', $html, $matches) === 1) {
            $avatar = trim($matches[1]);
            if ($avatar !== '') {
                $metadata['avatar_url'] = $avatar;
            }
        }

        $externalLinks = array_values(array_unique(array_filter($externalLinks, static fn (string $value): bool => $value !== '')));
        if ($externalLinks !== []) {
            $metadata['external_links'] = $externalLinks;
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
        if (isset($metadata['is_verified'])) {
            $summary['Verified'] = $metadata['is_verified'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractNextData(string $html): ?array
    {
        if (preg_match('/<script[^>]*id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/is', $html, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[1], true);

        return is_array($decoded) ? $decoded : null;
    }
}
