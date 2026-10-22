<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/deviantart.py
// parity-class: manual-june

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DeviantartValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'deviantart';
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
        return 'Deviantart';
    }

    public function siteUrl(): string
    {
        return 'https://www.deviantart.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.deviantart.com/'.rawurlencode($target);
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
        // Translation dictionaries contain error-page phrases even on real profiles.
        $visible = html_entity_decode(strip_tags((string) preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $body)), ENT_QUOTES | ENT_HTML5);

        if ($status === 404 || preg_match('~<(?:title|h[1-3])\b[^>]*>\s*Llama Not Found\s*</~i', $body) === 1) {
            return ['Available', ''];
        }

        if (stripos($visible, "We'll be back soon") !== false
            || stripos($visible, 'currently under temporary maintenance') !== false) {
            return ['Error', 'DeviantArt is temporarily unavailable for maintenance'];
        }

        if ($status === 200 && $this->containsProfileUrlMarker($body, $target)) {
            return ['Taken', ''];
        }

        // Renamed accounts redirect to a new handle (e.g. deviantart -> team).
        $effective = $response->effectiveUri();
        $renamed = $effective === null ? null : trim($effective->getPath(), '/');
        if ($status === 200 && $effective?->getHost() === 'www.deviantart.com'
            && is_string($renamed) && preg_match('/^[a-z0-9-]+$/i', $renamed) === 1
            && $this->containsProfileUrlMarker($body, $renamed)
            && preg_match('~\bdata-username=["\']'.preg_quote($renamed, '~').'["\']~i', $body) === 1) {
            return ['Taken', ''];
        }

        if ($status === 200) {
            return ['Error', 'DeviantArt returned HTTP 200 without a verifiable profile marker'];
        }

        return ['Error', 'Unexpected response status: '.$status];
    }

    private function containsProfileUrlMarker(string $html, string $target): bool
    {
        $expected = strtolower('https://www.deviantart.com/'.rawurlencode($target));

        if (preg_match_all('/<(?:meta|link)\b[^>]*>/i', $html, $tags) === false) {
            return false;
        }

        foreach ($tags[0] as $tag) {
            $isProfileMarker = preg_match('/\bproperty=["\']og:url["\']/i', $tag) === 1
                || preg_match('/\brel=["\']canonical["\']/i', $tag) === 1;
            if (! $isProfileMarker
                || preg_match('/\b(?:content|href)=["\']([^"\']+)["\']/i', $tag, $url) !== 1) {
                continue;
            }

            $candidate = strtolower(rtrim(html_entity_decode($url[1], ENT_QUOTES | ENT_HTML5), '/'));
            if ($candidate === rtrim($expected, '/')) {
                return true;
            }
        }

        return false;
    }
}
