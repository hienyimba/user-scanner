<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/habr.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HabrValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'habr';
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
        return 'Habr';
    }

    public function siteUrl(): string
    {
        return 'https://habr.com/ru/users/{user}/';
    }

    protected function requestUrl(string $target): string
    {
        return "https://habr.com/ru/users/{$target}/";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

protected function parseConnectorResponse(Response $response, string $target): array
{
    $status = $response->status();

    if ($status === 404) {
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
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $html = $response->body();
        if (preg_match('/"authorRefs":(\{.*?\})(?=,"authorIds")/s', $html, $matches) !== 1) {
            return [];
        }

        $json = str_replace('undefined', 'null', $matches[1]);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $userKey = null;
        foreach (array_keys($data) as $key) {
            if ($key !== '__ALIAS_STORE__') {
                $userKey = $key;
                break;
            }
        }

        if ($userKey === null || !is_array($data[$userKey] ?? null)) {
            return [];
        }

        $user = $data[$userKey];
        $metadata = [
            'username' => $target,
            'sources' => ['html_hydration'],
        ];

        $displayName = trim((string) ($user['fullname'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $speciality = trim((string) ($user['speciality'] ?? ''));
        if ($speciality !== '') {
            $metadata['speciality'] = $speciality;
        }

        if (isset($user['rating']) && is_numeric($user['rating'])) {
            $metadata['rating'] = (float) $user['rating'];
        }

        $karma = data_get($user, 'scoreStats.score');
        if (is_numeric($karma)) {
            $metadata['karma'] = (float) $karma;
        }

        $followers = data_get($user, 'followStats.followersCount');
        if (is_numeric($followers)) {
            $metadata['followers'] = (int) $followers;
        }

        $following = data_get($user, 'followStats.followCount');
        if (is_numeric($following)) {
            $metadata['following'] = (int) $following;
        }

        $posts = data_get($user, 'counterStats.postCount');
        if (is_numeric($posts)) {
            $metadata['posts_count'] = (int) $posts;
        }

        $comments = data_get($user, 'counterStats.commentCount');
        if (is_numeric($comments)) {
            $metadata['comments_count'] = (int) $comments;
        }

        $registered = trim((string) ($user['registerDateTime'] ?? ''));
        if ($registered !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($registered))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $registered;
            }
        }

        $avatar = trim((string) ($user['avatarUrl'] ?? ''));
        if ($avatar !== '') {
            $metadata['avatar_url'] = $avatar;
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
        if (is_string($metadata['speciality'] ?? null) && $metadata['speciality'] !== '') {
            $summary['Speciality'] = $metadata['speciality'];
        }
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (isset($metadata['posts_count'])) {
            $summary['Posts'] = (string) $metadata['posts_count'];
        }

        return $this->metadataSummary($summary);
    }
}
