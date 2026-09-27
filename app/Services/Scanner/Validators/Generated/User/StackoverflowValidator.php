<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class StackoverflowValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'stackoverflow';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Stackoverflow';
    }

    public function siteUrl(): string
    {
        return 'https://stackoverflow.com/users/filter?search={user}';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://api.stackexchange.com/2.3/users';
    }

    protected function requestQuery(string $target): array
    {
        return [
            'order' => 'desc',
            'sort' => 'name',
            'inname' => $target,
            'site' => 'stackoverflow',
        ];
    }

    protected function requestHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected Stack Exchange API status: '.$response->status()];
        }

        $data = $response->json();
        if (! is_array($data) || ! is_array($data['items'] ?? null)) {
            return ['Error', 'Unexpected Stack Exchange API response'];
        }

        return $this->matchingUser($data['items'], $target) !== null
            ? ['Taken', '']
            : ['Available', ''];
    }

    /** @return array<string, mixed> */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $items = $response->json('items');
        $user = is_array($items) ? $this->matchingUser($items, $target) : null;
        if ($status !== 'Taken' || $user === null) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'display_name' => html_entity_decode((string) $user['display_name'], ENT_QUOTES | ENT_HTML5),
            'sources' => ['stackexchange_api'],
        ];

        foreach ([
            'user_id' => 'stackoverflow_user_id',
            'location' => 'location',
            'reputation' => 'reputation',
            'profile_image' => 'avatar_url',
            'creation_date' => 'created_at_unix',
            'last_access_date' => 'last_active_at_unix',
        ] as $source => $destination) {
            if (($user[$source] ?? null) !== null && $user[$source] !== '') {
                $metadata[$destination] = $user[$source];
            }
        }

        if (! empty($user['link'])) {
            $metadata['external_links'] = [(string) $user['link']];
        }

        return $metadata;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>|null
     */
    private function matchingUser(array $items, string $target): ?array
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $displayName = html_entity_decode((string) ($item['display_name'] ?? ''), ENT_QUOTES | ENT_HTML5);
            if (strcasecmp(trim($displayName), $target) === 0) {
                return $item;
            }
        }

        return null;
    }
}
