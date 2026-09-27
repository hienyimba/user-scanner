<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/academia.py
// parity-class: generated

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AcademiaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'academia';
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
        return 'Academia';
    }

    public function siteUrl(): string
    {
        return 'https://independent.academia.edu/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://independent.academia.edu/{$target}";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        if (str_contains($target, '.')) {
            return new ScanResult(
                $target,
                $this->category(),
                $this->siteName(),
                'https://independent.academia.edu/'.rawurlencode($target),
                'Error',
                'Handles containing a period are not addressable',
                mode: $this->mode(),
                key: $this->key(),
            );
        }

        return parent::check($target, $options);
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 404) {
            return str_contains(strtolower($body), 'wrong aisle')
                ? ['Available', '']
                : ['Error', 'Unexpected 404 without Academia not-found marker'];
        }

        if ($status !== 200) {
            return ['Error', 'Unexpected Academia response status: '.$status];
        }

        $profile = $this->profileJson($body);
        if ($profile === null) {
            return ['Error', 'Profile confirmation not found'];
        }

        $canonicalUrl = (string) data_get($profile, 'mainEntity.url', '');
        $handle = rawurldecode((string) basename(rtrim($canonicalUrl, '/')));
        if ($handle === '' || strcasecmp($handle, $target) !== 0) {
            return ['Error', 'Profile resolved to a different handle: '.$handle];
        }

        return ['Taken', ''];
    }

    /** @return array<string, mixed> */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $profile = $this->profileJson($response->body());
        if ($status !== 'Taken' || $profile === null) {
            return [];
        }

        $person = is_array($profile['mainEntity'] ?? null) ? $profile['mainEntity'] : [];
        $metadata = [
            'username' => $target,
            'sources' => ['json_ld'],
        ];

        if (! empty($person['name'])) {
            $metadata['display_name'] = trim((string) $person['name']);
        }
        if (! empty($person['image']) && ! str_contains((string) $person['image'], 'no_pic')) {
            $metadata['avatar_url'] = (string) $person['image'];
        }
        if (! empty($profile['description'])) {
            $metadata['bio'] = trim((string) $profile['description']);
        }
        if (! empty($profile['dateCreated'])) {
            $metadata['created_at'] = substr((string) $profile['dateCreated'], 0, 10);
        }

        $links = $person['sameAs'] ?? $profile['sameAs'] ?? [];
        if (is_array($links)) {
            $metadata['external_links'] = array_values(array_filter($links, 'is_string'));
        }

        return $metadata;
    }

    /** @return array<string, mixed>|null */
    private function profileJson(string $html): ?array
    {
        if (preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $blocks) < 1) {
            return null;
        }

        foreach ($blocks[1] as $block) {
            $data = json_decode(html_entity_decode($block, ENT_QUOTES | ENT_HTML5), true);
            if (is_array($data) && ($data['@type'] ?? null) === 'ProfilePage') {
                return $data;
            }
        }

        return null;
    }
}
