<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class HackernewsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackernews';
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
        return 'Hackernews';
    }

    public function siteUrl(): string
    {
        return 'https://news.ycombinator.com/user?id={user}';
    }

    protected function requestUrl(string $target): string
    {
        // Official Hacker News API (https://github.com/HackerNews/API). The
        // Firebase endpoint is CDN-served and does not apply the aggressive
        // per-IP scrape rate limit that news.ycombinator.com/user enforces
        // under batch load.
        return 'https://hacker-news.firebaseio.com/v0/user/' . rawurlencode($target) . '.json';
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status !== 200) {
            return ['Error', 'Unexpected status: ' . $status];
        }

        // Missing users deserialize to a literal JSON `null`.
        if (trim($response->body()) === 'null') {
            return ['Available', ''];
        }

        $data = $response->json();
        if (is_array($data) && !empty($data['id'])) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected response body'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $metadata = parent::buildStructuredMetadata($response, $target, $status);
        if (!in_array($status, ['Taken', 'Found'], true) || $response->status() !== 200) {
            return $metadata;
        }

        $data = $response->json();
        if (!is_array($data) || empty($data['id'])) {
            return $metadata;
        }

        $username = trim((string) $data['id']);
        $metadata['username'] ??= $username;
        $metadata['display_name'] ??= $username;
        $metadata['sources'] = array_values(array_unique(array_merge((array) ($metadata['sources'] ?? []), ['api_json'])));

        $about = trim((string) ($data['about'] ?? ''));
        if ($about !== '') {
            $bio = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($about, ENT_QUOTES | ENT_HTML5))) ?? '');
            if ($bio !== '') {
                $metadata['bio'] ??= $bio;
            }

            if (preg_match_all('/href="([^"]+)"/i', $about, $linkMatches) !== false) {
                $links = $metadata['external_links'] ?? [];
                foreach ($linkMatches[1] as $href) {
                    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));
                    if ($href !== '' && (str_starts_with($href, 'http://') || str_starts_with($href, 'https://'))) {
                        $links[] = $href;
                    }
                }
                $metadata['external_links'] = array_values(array_unique($links));
            }
        }

        if (is_numeric($data['created'] ?? null)) {
            try {
                $metadata['created_at'] ??= (new \DateTimeImmutable('@' . (string) $data['created']))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                // Ignore invalid upstream timestamps.
            }
        }

        if (is_numeric($data['karma'] ?? null)) {
            $metadata['karma'] = (int) $data['karma'];
        }

        if (is_array($data['submitted'] ?? null)) {
            $metadata['posts_count'] = count($data['submitted']);
        }

        return $metadata;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Taken', 'Found'], true) || $response->status() !== 200) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (isset($metadata['karma'])) {
            $summary['Karma'] = (string) $metadata['karma'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created At'] = $metadata['created_at'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }

        return $this->metadataSummary($summary);
    }
}
