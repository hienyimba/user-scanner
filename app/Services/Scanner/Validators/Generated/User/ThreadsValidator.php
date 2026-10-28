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
        return 'https://www.threads.com/@{user}';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.threads.com/@'.rawurlencode($target);
    }

    protected function timeoutSeconds(): int
    {
        return 15;
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer' => 'https://www.threads.com/',
            'Sec-Fetch-Mode' => 'navigate',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $body = $response->body();

        if ($response->status() === 200
            && preg_match('/<meta[^>]+(?:property|name)="og:type"[^>]+content="profile"/i', $body) === 1
            && $this->canonicalMatches($body, $target)) {
            return ['Taken', ''];
        }

        if ($response->status() === 200
            && (str_contains($body, '<title>Threads • Log in</title>')
                || str_contains($body, 'content="https://www.threads.com/login'))) {
            return ['Available', ''];
        }

        return ['Error', 'Threads response contained neither a confirmed profile nor login fallback'];
    }

    /** @return array<string, mixed> */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if ($status !== 'Taken') {
            return [];
        }

        $body = $response->body();
        $metadata = [
            'username' => $target,
            'sources' => ['profile_html'],
        ];

        $title = $this->meta($body, 'og:title');
        if ($title !== null) {
            $displayName = trim((string) preg_replace('/\s*\(@[^)]+\).*$/u', '', $title));
            if ($displayName !== '' && strcasecmp($displayName, $target) !== 0) {
                $metadata['display_name'] = $displayName;
            }
        }

        $description = $this->meta($body, 'og:description');
        if ($description !== null) {
            $metadata['bio'] = $description;
        }

        $avatar = $this->meta($body, 'og:image');
        if ($avatar !== null) {
            $metadata['avatar_url'] = $avatar;
        }

        return $metadata;
    }

    private function canonicalMatches(string $html, string $target): bool
    {
        if (preg_match('/<link[^>]+rel="canonical"[^>]+href="([^"]+)"/i', $html, $matches) !== 1) {
            return false;
        }

        $path = (string) parse_url(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5), PHP_URL_PATH);

        return strcasecmp(rawurldecode(trim($path, '/')), '@'.$target) === 0;
    }

    private function meta(string $html, string $property): ?string
    {
        $quoted = preg_quote($property, '/');
        if (preg_match('/<meta[^>]+(?:property|name)="'.$quoted.'"[^>]+content="([^"]*)"/i', $html, $matches) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $value !== '' ? $value : null;
    }
}
