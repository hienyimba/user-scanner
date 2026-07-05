<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/gitee.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class GiteeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'gitee';
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
        return 'Gitee';
    }

    public function siteUrl(): string
    {
        return 'https://gitee.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://gitee.com/api/v5/users/{$target}";
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
    $body = $response->body();

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

        $data = $response->json();
        if (!is_array($data) || !isset($data['id'])) {
            return [];
        }

        $metadata = [
            'username' => trim((string) ($data['login'] ?? '')) ?: $target,
            'sources' => ['api_json'],
        ];

        $metadata['gitee_id'] = is_numeric($data['id']) ? (int) $data['id'] : (string) $data['id'];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $metadata['display_name'] = $name;
        }

        $bio = trim((string) ($data['bio'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $blog = trim((string) ($data['blog'] ?? ''));
        if ($blog !== '') {
            $metadata['website_url'] = $blog;
            $metadata['external_links'] = [$blog];
        }

        foreach ([
            'public_repos' => 'posts_count',
            'followers' => 'followers',
            'following' => 'following',
        ] as $sourceKey => $metadataKey) {
            if (isset($data[$sourceKey]) && is_numeric($data[$sourceKey])) {
                $metadata[$metadataKey] = (int) $data[$sourceKey];
            }
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

        if (isset($metadata['gitee_id'])) {
            $summary['ID'] = (string) $metadata['gitee_id'];
        }
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

        return $this->metadataSummary($summary);
    }
}
