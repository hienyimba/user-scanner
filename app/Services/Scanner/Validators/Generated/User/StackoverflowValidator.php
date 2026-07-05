<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use DOMDocument;
use DOMXPath;
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
        return "https://stackoverflow.com/users/filter?search={$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 200) {
            if (str_contains($body, 'No users matched your search.')) {
                return ['Available', ''];
            }

            if (str_contains($body, '>' . $target . '<')) {
                return ['Taken', ''];
            }

            return ['Available', ''];
        }

        return ['Error', 'Unexpected status code from Stack Overflow'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true) || $response->status() !== 200) {
            return [];
        }

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($response->body());
        libxml_clear_errors();
        if ($loaded === false) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//div[contains(@class, "user-info")]');
        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $link = $xpath->query('.//a[contains(@href, "/users/")][1]', $node)?->item(0);
            if ($link === null) {
                continue;
            }

            $href = trim((string) $link->attributes?->getNamedItem('href')?->nodeValue);
            $name = trim((string) $link->textContent);
            $slug = '';
            if (preg_match('#/users/\d+/([^/?\#]+)#', $href, $matches) === 1) {
                $slug = strtolower(trim($matches[1]));
            }

            if ($slug !== strtolower($target) && strtolower($name) !== strtolower($target)) {
                continue;
            }

            $metadata = [
                'username' => $target,
                'sources' => ['profile_html'],
            ];

            $locationNode = $xpath->query('.//*[contains(@class, "user-location")]', $node)?->item(0);
            $location = trim((string) ($locationNode?->textContent ?? ''));
            if ($location !== '') {
                $metadata['location'] = preg_replace('/\s+/', ' ', $location) ?: $location;
            }

            $avatarNode = $xpath->query('.//img[1]', $node)?->item(0);
            $avatar = trim((string) ($avatarNode?->attributes?->getNamedItem('src')?->nodeValue ?? ''));
            if ($avatar !== '') {
                if (str_starts_with($avatar, '//')) {
                    $avatar = 'https:' . $avatar;
                }
                $metadata['avatar_url'] = str_replace('&amp;', '&', $avatar);
            }

            $repNode = $xpath->query('.//*[@title[contains(., "total reputation")]][1]', $node)?->item(0);
            $repTitle = trim((string) ($repNode?->attributes?->getNamedItem('title')?->nodeValue ?? ''));
            if ($repTitle !== '' && preg_match('/total reputation:\s*([^"]+)/i', $repTitle, $matches) === 1) {
                $metadata['reputation'] = trim($matches[1]);
            }

            return $metadata;
        }

        return [];
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];

        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['reputation'] ?? null) && $metadata['reputation'] !== '') {
            $summary['Reputation'] = $metadata['reputation'];
        }
        if (is_string($metadata['avatar_url'] ?? null) && $metadata['avatar_url'] !== '') {
            $summary['Avatar'] = $metadata['avatar_url'];
        }

        return $this->metadataSummary($summary);
    }
}
