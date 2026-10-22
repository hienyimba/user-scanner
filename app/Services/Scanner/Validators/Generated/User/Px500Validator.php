<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// Upstream's GraphQL request was corrected in PR #604 to keep the username
// out of the raw query string. The api.500px.com host has since disappeared,
// so this port uses the public profile's server-rendered crawler response.

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class Px500Validator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'px500';
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
        return 'Px500';
    }

    public function siteUrl(): string
    {
        return 'https://500px.com/p/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://500px.com/p/'.rawurlencode($target);
    }

    protected function requestHeaders(): array
    {
        return [
            // 500px's ordinary response is now a client-rendered shell. Its
            // crawler response contains the canonical profile metadata and a
            // rendered not-found component, which gives both explicit states.
            'User-Agent' => 'Twitterbot/1.0',
            'Accept' => 'text/html,application/xhtml+xml',
        ];
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function detectBlockedOrChallenged(Response $response): ?array
    {
        if ($response->status() === 200) {
            $body = $response->body();

            // The server-rendered payload includes translated anti-bot copy in
            // its JavaScript state. Explicit rendered profile/not-found markers
            // take precedence so those inert strings are not false challenges.
            if ($this->hasRenderedUnavailableMarker($body)
                || $this->metaContent($body, 'property', 'og:url') !== null) {
                return null;
            }
        }

        return parent::detectBlockedOrChallenged($response);
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($status === 200) {
            $data = $response->json();
            if (is_array($data)) {
                if (data_get($data, 'data.userByUsername') !== null) {
                    return ['Taken', ''];
                }

                if (data_get($data, 'errors.0.extensions.response.status') === 404
                    || data_get($data, 'data.userByUsername') === null) {
                    return ['Available', ''];
                }
            }

            $body = $response->body();
            if ($this->hasRenderedUnavailableMarker($body)) {
                return ['Available', ''];
            }

            $profileUrl = 'https://500px.com/p/'.rawurlencode($target);
            if ($this->metaContent($body, 'property', 'og:url') === $profileUrl) {
                return ['Taken', ''];
            }
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }

    private function hasRenderedUnavailableMarker(string $html): bool
    {
        return preg_match('/<[^>]+>\s*This user is unavailable\s*<\/[^>]+>/i', $html) === 1;
    }

    private function metaContent(string $html, string $attribute, string $expectedValue): ?string
    {
        if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags) === false) {
            return null;
        }

        foreach ($tags[0] as $tag) {
            if (preg_match('/\b'.preg_quote($attribute, '/').'=["\']'.preg_quote($expectedValue, '/').'["\']/i', $tag) !== 1
                || preg_match('/\bcontent=["\']([^"\']+)["\']/i', $tag, $content) !== 1) {
                continue;
            }

            return html_entity_decode($content[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }
}
