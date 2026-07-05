<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class PinterestValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'pinterest';
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
        return 'Pinterest';
    }

    public function siteUrl(): string
    {
        return 'https://www.pinterest.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.pinterest.com/{$target}/";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            if (str_contains($response->body(), 'User not found.')) {
                return ['Available', ''];
            }

            return ['Taken', ''];
        }

        return ['Error', 'Invalid status code'];
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

        $props = $this->extractPinterestProps($response->body());
        $users = is_array(data_get($props, 'initialReduxState.users')) ? data_get($props, 'initialReduxState.users') : [];
        if (!is_array($users) || $users === []) {
            return $fallback;
        }

        $userData = null;
        foreach ($users as $userKey => $candidate) {
            if ($userKey === '' || !is_array($candidate)) {
                continue;
            }

            $userData = $candidate;

            break;
        }

        if (!is_array($userData)) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['html_hydration']),
        ];

        $displayName = $this->cleanString($userData['full_name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $bio = $this->cleanString($userData['about'] ?? null);
        if ($bio !== null) {
            $metadata['bio'] = $bio;
        }

        $followers = $this->normalizeInteger($userData['follower_count'] ?? null);
        if ($followers !== null) {
            $metadata['followers'] = $followers;
        }

        $following = $this->normalizeInteger($userData['following_count'] ?? null);
        if ($following !== null) {
            $metadata['following'] = $following;
        }

        $boardsCount = $this->normalizeInteger($userData['board_count'] ?? null);
        if ($boardsCount !== null) {
            $metadata['boards_count'] = $boardsCount;
        }

        $pinsCount = $this->normalizeInteger($userData['pin_count'] ?? null);
        if ($pinsCount !== null) {
            $metadata['pins_count'] = $pinsCount;
            $metadata['posts_count'] = $pinsCount;
        }

        $websiteUrl = $this->cleanString($userData['website_url'] ?? null);
        if ($websiteUrl !== null) {
            $metadata['website_url'] = $websiteUrl;
            $metadata['external_links'] = [$websiteUrl];
        }

        $avatarUrl = $this->cleanString($userData['image_xlarge_url'] ?? null)
            ?? $this->cleanString($userData['image_medium_url'] ?? null);
        if ($avatarUrl !== null) {
            $metadata['avatar_url'] = $avatarUrl;
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
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (isset($metadata['following'])) {
            $summary['Following'] = (string) $metadata['following'];
        }
        if (isset($metadata['boards_count'])) {
            $summary['Boards'] = (string) $metadata['boards_count'];
        }
        if (isset($metadata['pins_count'])) {
            $summary['Pins'] = (string) $metadata['pins_count'];
        }
        if (is_string($metadata['website_url'] ?? null) && $metadata['website_url'] !== '') {
            $summary['Website'] = $metadata['website_url'];
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPinterestProps(string $html): ?array
    {
        if (preg_match('/<script[^>]*id="__PWS_INITIAL_PROPS__"[^>]*>(.*?)<\/script>/is', $html, $matches) !== 1) {
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

    private function normalizeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
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
