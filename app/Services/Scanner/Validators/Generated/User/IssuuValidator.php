<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/issuu.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class IssuuValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'issuu';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Issuu';
    }

    public function siteUrl(): string
    {
        return 'https://issuu.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://issuu.com/query?format=json&_=3210224608766&profileUsername={$target}&action=issuu.user.get_anonymous";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Accept' => 'application/json',
            'Referer' => "https://issuu.com/{$target}",
        ];
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 404 || str_contains($body, 'No such user') || str_contains(strtolower($body), 'no such user')) {
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
        $fallback = parent::buildStructuredMetadata($response, $target, $status);
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return $fallback;
        }

        $profile = data_get($response->json(), 'rsp._content.profile');
        if (!is_array($profile)) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['api_json']),
        ];

        $displayName = $this->cleanString($profile['displayName'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $bio = $this->cleanString($profile['about'] ?? null);
        if ($bio !== null) {
            $metadata['bio'] = $bio;
        }

        $location = $this->cleanString($profile['location'] ?? null);
        if ($location !== null) {
            $metadata['location'] = $location;
        }

        $websiteUrl = $this->cleanString($profile['website'] ?? null);
        if ($websiteUrl !== null) {
            $metadata['website_url'] = $websiteUrl;
            $metadata['external_links'] = [$websiteUrl];
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
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['website_url'] ?? null) && $metadata['website_url'] !== '') {
            $summary['Website'] = $metadata['website_url'];
        }

        return $this->metadataSummary($summary);
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
