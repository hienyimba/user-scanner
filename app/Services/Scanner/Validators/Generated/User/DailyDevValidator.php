<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/daily_dev.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DailyDevValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'daily_dev';
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
        return 'DailyDev';
    }

    public function siteUrl(): string
    {
        return 'https://daily.dev/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://daily.dev/{$target}";
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
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status !== 200) {
            return ['Error', 'daily_dev: unexpected response body (non-200 status '.$status.')'];
        }

        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $body, $matches) !== 1) {
            return ['Error', 'daily_dev: unexpected response body (no __NEXT_DATA__ payload)'];
        }

        $decoded = json_decode($matches[1], true);
        if (! is_array($decoded)) {
            return ['Error', 'daily_dev: unexpected response body (malformed __NEXT_DATA__)'];
        }

        $pageProps = $decoded['props']['pageProps'] ?? null;
        if (! is_array($pageProps)) {
            return ['Error', 'daily_dev: unexpected response body (missing pageProps)'];
        }

        $userData = $pageProps['user'] ?? null;
        if (is_array($userData) && ! empty($userData['id'])) {
            return ['Taken', ''];
        }

        $noindex = $pageProps['noindex'] ?? null;
        if ($noindex === true) {
            return ['Available', ''];
        }

        if ($noindex === false) {
            // Indexed public profile; user data may be fetched client-side.
            return ['Taken', ''];
        }

        // Ambiguous empty pageProps (daily.dev edge inconsistency): retryable
        // so the proxy rotation policy can re-attempt on another exit, which
        // typically serves a clean noindex/user signal.
        return ['Error', 'daily_dev: unexpected response body (empty pageProps)'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $fallback = parent::buildStructuredMetadata($response, $target, $status);
        if (! in_array($status, ['Taken', 'Found'], true)) {
            return $fallback;
        }

        $nextData = $this->extractNextData($response->body());
        $userData = is_array($nextData['props']['pageProps']['user'] ?? null)
            ? $nextData['props']['pageProps']['user']
            : null;
        $userStats = is_array($nextData['props']['pageProps']['userStats'] ?? null)
            ? $nextData['props']['pageProps']['userStats']
            : [];

        if ($userData === null) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['html_hydration']),
        ];

        $displayName = $this->cleanString($userData['name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $userId = $this->cleanString($userData['id'] ?? null);
        if ($userId !== null) {
            $metadata['daily_dev_user_id'] = $userId;
        }

        foreach ([
            'bio' => 'bio',
            'createdAt' => 'created_at',
            'reputation' => 'reputation',
        ] as $source => $destination) {
            if (($userData[$source] ?? null) !== null && $userData[$source] !== '') {
                $metadata[$destination] = $userData[$source];
            }
        }

        if (is_numeric($userStats['numFollowers'] ?? null)) {
            $metadata['followers'] = (int) $userStats['numFollowers'];
        }
        if (is_numeric($userStats['numFollowing'] ?? null)) {
            $metadata['following'] = (int) $userStats['numFollowing'];
        }

        return array_replace($fallback, $metadata);
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (! in_array($status, ['Taken', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];

        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['daily_dev_user_id'] ?? null) && $metadata['daily_dev_user_id'] !== '') {
            $summary['User ID'] = $metadata['daily_dev_user_id'];
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

    private function cleanString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int, string>  $newSources
     * @return array<int, string>
     */
    private function mergeSources(mixed $existing, array $newSources): array
    {
        $sources = is_array($existing) ? $existing : [];
        $merged = [];

        foreach (array_merge($sources, $newSources) as $source) {
            if (! is_string($source) || trim($source) === '') {
                continue;
            }

            $merged[] = trim($source);
        }

        return array_values(array_unique($merged));
    }
}
