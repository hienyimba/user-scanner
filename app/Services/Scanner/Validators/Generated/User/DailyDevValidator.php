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
        return 'https://app.daily.dev';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://app.daily.dev/{$target}";
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
            return ['Error', 'Could not read __NEXT_DATA__ payload, report it via GitHub issues.'];
        }

        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $body, $matches) !== 1) {
            return ['Error', 'Could not read __NEXT_DATA__ payload, report it via GitHub issues.'];
        }

        $decoded = json_decode($matches[1], true);
        if (!is_array($decoded)) {
            return ['Error', 'Could not read __NEXT_DATA__ payload, report it via GitHub issues.'];
        }

        $pageProps = $decoded['props']['pageProps'] ?? null;
        if (!is_array($pageProps)) {
            return ['Error', 'Unexpected daily.dev payload shape, report it via GitHub issues.'];
        }

        $userData = $pageProps['user'] ?? null;
        if (is_array($userData) && !empty($userData['id']) && !empty($userData['name'])) {
            return ['Taken', ''];
        }

        if (($pageProps['noindex'] ?? null) === true) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected daily.dev payload shape, report it via GitHub issues.'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $fallback = parent::buildStructuredMetadata($response, $target, $status);
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return $fallback;
        }

        $nextData = $this->extractNextData($response->body());
        $userData = is_array($nextData['props']['pageProps']['user'] ?? null)
            ? $nextData['props']['pageProps']['user']
            : null;

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

        return array_replace($fallback, $metadata);
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
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param mixed $existing
     * @param array<int, string> $newSources
     * @return array<int, string>
     */
    private function mergeSources(mixed $existing, array $newSources): array
    {
        $sources = is_array($existing) ? $existing : [];
        $merged = [];

        foreach (array_merge($sources, $newSources) as $source) {
            if (!is_string($source) || trim($source) === '') {
                continue;
            }

            $merged[] = trim($source);
        }

        return array_values(array_unique($merged));
    }
}
