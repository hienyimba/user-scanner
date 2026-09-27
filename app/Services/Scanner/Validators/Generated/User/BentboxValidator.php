<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BentboxValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bentbox';
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
        return 'Bentbox';
    }

    public function siteUrl(): string
    {
        return 'https://bentbox.co/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://bentbox.co/{$target}";
    }

    protected function followRedirects(): bool
    {
        return false;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();

        if ($response->status() === 200 && $this->profilePerson($body) !== null) {
            return ['Taken', ''];
        }

        if (str_contains($body, 'User not found')) {
            return ['Available', ''];
        }

        if ($response->redirect()) {
            return ['Error', 'Redirected without a profile verdict'];
        }

        return ['Error', 'Neither a profile nor the not-found marker was present'];
    }

    /** @return array<string, mixed> */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if ($status !== 'Taken') {
            return [];
        }

        $person = $this->profilePerson($response->body());
        if ($person === null) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['json_ld'],
        ];

        if (is_scalar($person['name'] ?? null) && trim((string) $person['name']) !== '') {
            $metadata['display_name'] = trim((string) $person['name']);
        }
        if (is_scalar($person['identifier'] ?? null)) {
            $metadata['profile_id'] = (string) $person['identifier'];
        }

        if (preg_match('/property="og:image" content="([^"]+)"/i', $response->body(), $avatar) === 1
            && ! str_contains($avatar[1], '/img/Icon_BentBox')) {
            $metadata['avatar_url'] = html_entity_decode($avatar[1], ENT_QUOTES | ENT_HTML5);
        }

        foreach ((array) ($person['interactionStatistic'] ?? []) as $counter) {
            if (is_array($counter)
                && str_ends_with((string) ($counter['interactionType'] ?? ''), '/FollowAction')
                && is_numeric($counter['userInteractionCount'] ?? null)) {
                $metadata['followers'] = (int) $counter['userInteractionCount'];
            }
        }

        return $metadata;
    }

    /** @return array<string, mixed>|null */
    private function profilePerson(string $html): ?array
    {
        if (preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $blocks) < 1) {
            return null;
        }

        foreach ($blocks[1] as $block) {
            $data = json_decode(html_entity_decode($block, ENT_QUOTES | ENT_HTML5), true);
            if (is_array($data)
                && ($data['@type'] ?? null) === 'ProfilePage'
                && is_array($data['mainEntity'] ?? null)) {
                return $data['mainEntity'];
            }
        }

        return null;
    }
}
