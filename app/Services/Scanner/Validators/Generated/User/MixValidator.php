<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MixValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'mix';
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
        return 'Mix';
    }

    public function siteUrl(): string
    {
        return 'https://mix.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://mix.com/'.rawurlencode($target);
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => config('scanner.user_agent'),
        ];
    }

    protected function usesProxy(): bool
    {
        // Mix returns reliable 404/profile responses directly, while proxied
        // requests frequently receive a generic page with no profile evidence.
        return false;
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status !== 200) {
            return ['Error', 'HTTP '.$status];
        }

        $body = $response->body();
        $canonical = $this->extractCanonicalUrl($body);
        $expectedUrl = 'https://mix.com/'.rawurlencode($target);
        $title = $this->extractTitle($body);
        $hasMatchingTitle = $title !== null
            && str_contains(strtolower($title), '(@'.strtolower($target).')')
            && str_contains(strtolower($title), 'mix');

        if ($canonical !== null && strcasecmp(rtrim($canonical, '/'), $expectedUrl) === 0 && $hasMatchingTitle) {
            return ['Taken', ''];
        }

        return ['Error', 'Mix response did not contain matching profile evidence'];
    }

    private function extractCanonicalUrl(string $body): ?string
    {
        if (preg_match('/<link\b(?=[^>]*\brel=["\']canonical["\'])[^>]*\bhref=["\']([^"\']+)["\']/i', $body, $matches) !== 1) {
            return null;
        }

        return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function extractTitle(string $body): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches) !== 1) {
            return null;
        }

        return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
