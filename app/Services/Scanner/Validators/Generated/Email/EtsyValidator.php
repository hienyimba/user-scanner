<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class EtsyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'etsy';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Etsy';
    }

    public function siteUrl(): string
    {
        return 'https://www.etsy.com';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.etsy.com/api/v3/ajax/public/users/by-identity-optional';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Referer' => 'https://www.etsy.com/join/email',
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'identity' => $target,
        ];
    }

    protected function requestBodyMode(): string
    {
        return 'form';
    }

    protected function requestBody(string $target): array
    {
        return [];
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

protected function parseConnectorResponse(Response $response, string $target): array
{
    if ($response->status() === 403) {
        return ['Error', '403'];
    }
    if (trim($response->body()) === 'null') {
        return ['Not Registered', ''];
    }
    if (!empty(($response->json())['user_id'] ?? null)) {
        return ['Registered', ''];
    }
    return ['Error', 'Unexpected response body structure'];
}

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Registered', 'Found'], true)) {
            return [];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $metadata = [
            'public_email' => $target,
            'sources' => ['api_json'],
        ];

        if (isset($data['user_id']) && is_numeric($data['user_id'])) {
            $metadata['etsy_user_id'] = (int) $data['user_id'];
        }

        $displayName = trim((string) (($data['real_name'] ?? null) ?: ($data['display_name'] ?? null) ?: ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $username = trim((string) ($data['login_name'] ?? ''));
        if ($username !== '') {
            $metadata['username'] = $username;
        }

        $gender = trim((string) ($data['gender'] ?? ''));
        if ($gender !== '') {
            $metadata['gender'] = $gender;
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $bio = trim((string) ($data['bio'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        if (array_key_exists('is_seller', $data)) {
            $metadata['is_seller'] = (bool) $data['is_seller'];
        }

        if (array_key_exists('has_page', $data)) {
            $metadata['has_public_page'] = (bool) $data['has_page'];
        }

        if (array_key_exists('favorite_items_public', $data)) {
            $metadata['favorite_items_public'] = (bool) $data['favorite_items_public'];
        }

        if (array_key_exists('favorite_shops_public', $data)) {
            $metadata['favorite_shops_public'] = (bool) $data['favorite_shops_public'];
        }

        foreach ([
            'follower_count' => 'followers',
            'following_count' => 'following',
            'num_favorites' => 'favorites_count',
        ] as $sourceKey => $targetKey) {
            if (isset($data[$sourceKey]) && is_numeric($data[$sourceKey])) {
                $metadata[$targetKey] = (int) $data[$sourceKey];
            }
        }

        $avatar = trim((string) (($data['avatar']['url'] ?? null) ?: ($data['avatar_url'] ?? null) ?: ''));
        if ($avatar !== '') {
            $metadata['avatar_url'] = $avatar;
        }

        foreach ([
            'create_date' => 'created_at',
            'update_date' => 'last_active_at',
        ] as $sourceKey => $targetKey) {
            if (isset($data[$sourceKey]) && is_numeric($data[$sourceKey])) {
                try {
                    $metadata[$targetKey] = (new \DateTimeImmutable('@' . (string) $data[$sourceKey]))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->format(DATE_ATOM);
                } catch (\Throwable) {
                    // Keep metadata partial if timestamp normalization fails.
                }
            }
        }

        return $metadata;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Registered', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];

        if (isset($metadata['etsy_user_id'])) {
            $summary['User ID'] = (string) $metadata['etsy_user_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['username'] ?? null) && $metadata['username'] !== '') {
            $summary['Username'] = $metadata['username'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }

        return $this->metadataSummary($summary);
    }
}
