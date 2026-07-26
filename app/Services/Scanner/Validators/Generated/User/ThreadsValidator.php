<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ThreadsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'threads';
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
        return 'Threads';
    }

    public function siteUrl(): string
    {
        return 'https://www.threads.net/@{user}';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.threads.net/api/v1/users/web_profile_info/';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'X-IG-App-ID' => '936619743392459',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.9',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => "https://www.threads.net/@{$target}",
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'username' => $target,
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $status . ')'];
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

        $user = $response->json('data.user');
        if (!is_array($user) || $user === []) {
            return $fallback;
        }

        $metadata = [
            'username' => $this->cleanString($user['username'] ?? null) ?? $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['api_json']),
        ];

        $displayName = $this->cleanString($user['full_name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $bio = $this->cleanString($user['biography'] ?? null);
        if ($bio !== null) {
            $metadata['bio'] = $bio;
        }

        $avatarUrl = $this->cleanString($user['profile_pic_url'] ?? null);
        if ($avatarUrl !== null) {
            $metadata['avatar_url'] = $avatarUrl;
        }

        if (is_numeric($user['follower_count'] ?? null)) {
            $metadata['followers'] = (int) $user['follower_count'];
        }

        if (is_bool($user['is_verified'] ?? null)) {
            $metadata['is_verified'] = (bool) $user['is_verified'];
        }

        if (is_bool($user['is_private'] ?? null)) {
            $metadata['is_private'] = (bool) $user['is_private'];
        }

        $threadsUserId = $this->cleanString($user['id'] ?? null);
        if ($threadsUserId !== null) {
            $metadata['threads_user_id'] = $threadsUserId;
        }

        $externalLinks = [];
        foreach ((array) ($user['bio_links'] ?? []) as $bioLink) {
            $url = is_array($bioLink) ? $this->cleanString($bioLink['url'] ?? null) : null;
            if ($url !== null && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                $externalLinks[] = $url;
            }
        }
        if ($externalLinks !== []) {
            $metadata['external_links'] = array_values(array_unique($externalLinks));
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
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (array_key_exists('is_verified', $metadata)) {
            $summary['Verified'] = $metadata['is_verified'] ? 'Yes' : 'No';
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
