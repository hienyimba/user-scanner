<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BdsmlrValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bdsmlr';
    }

    public function category(): string
    {
        return 'adult';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Bdsmlr';
    }

    public function siteUrl(): string
    {
        return 'https://bdsmlr.com/blog/{user}';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://api-prod.bdsmlr.com/v2/api/get-blog';
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    protected function requestBody(string $target): array
    {
        return ['blog_name' => $target];
    }

    protected function requestHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $data = $response->json();
        if (is_array($data['blog'] ?? null) && ! empty($data['blog']['name'])) {
            return ['Taken', ''];
        }

        if (($data['error'] ?? null) === 'blog not found') {
            return ['Available', ''];
        }

        return ['Error', (string) ($data['error'] ?? 'Unexpected BDSMLR API response')];
    }

    /** @return array<string, mixed> */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $blog = $response->json('blog');
        if ($status !== 'Taken' || ! is_array($blog)) {
            return [];
        }

        $metadata = [
            'username' => (string) ($blog['name'] ?? $target),
            'sources' => ['api_json'],
        ];

        foreach ([
            'id' => 'blog_id',
            'ownerUserId' => 'owner_user_id',
            'title' => 'display_name',
            'postsCount' => 'posts_count',
            'followersCount' => 'followers',
        ] as $source => $destination) {
            if (($blog[$source] ?? null) !== null && $blog[$source] !== '') {
                $metadata[$destination] = $blog[$source];
            }
        }

        $description = trim((string) ($blog['description'] ?? ''));
        if ($description !== '' && strtolower($description) !== 'no description') {
            $metadata['bio'] = $description;
        }
        if (! empty($blog['avatarUrl'])) {
            $metadata['avatar_url'] = (string) $blog['avatarUrl'];
        }

        return $metadata;
    }
}
