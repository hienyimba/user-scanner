<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class Cups7Validator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'cups7';
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
        return 'Cups7';
    }

    public function siteUrl(): string
    {
        return 'https://www.7cups.com/@{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.7cups.com/apiv2/user/{$target}";
    }

    protected function requestHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() === 404) {
            return ['Available', ''];
        }

        if ($response->status() === 202 || trim((string) $response->header('x-amzn-waf-action')) !== '') {
            return ['Error', 'AWS WAF challenge triggered'];
        }

        if ($response->status() !== 200) {
            return ['Error', 'Unexpected response status: '.$response->status()];
        }

        $data = $response->json();
        if (is_array($data) && ! empty($data['screenName'])) {
            return ['Taken', ''];
        }

        return ['Error', '200 response with no profile data'];
    }

    /** @return array<string, mixed> */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $data = $response->json();
        if ($status !== 'Taken' || ! is_array($data)) {
            return [];
        }

        $metadata = [
            'username' => (string) ($data['screenName'] ?? $target),
            'sources' => ['api_json'],
        ];

        foreach ([
            'bio' => 'bio',
            'Country' => 'location',
            'language' => 'language',
            'listenerLevel' => 'level',
            'memberLevel' => 'level',
            'overallRating' => 'rating',
            'lastActive' => 'last_active_at',
        ] as $source => $destination) {
            if (($data[$source] ?? null) !== null && $data[$source] !== '') {
                $metadata[$destination] = is_string($data[$source])
                    ? trim(strip_tags($data[$source]))
                    : $data[$source];
            }
        }

        $userId = $data['userID'] ?? $data['memID'] ?? $data['listID'] ?? null;
        if ($userId !== null) {
            $metadata['user_id'] = $userId;
        }
        if (! empty($data['imgURL'])) {
            $metadata['avatar_url'] = str_starts_with((string) $data['imgURL'], 'http')
                ? (string) $data['imgURL']
                : 'https://www.7cups.com'.$data['imgURL'];
        }

        return $metadata;
    }
}
