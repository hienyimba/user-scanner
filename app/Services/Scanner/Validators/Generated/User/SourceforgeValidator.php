<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class SourceforgeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'sourceforge';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Sourceforge';
    }

    public function siteUrl(): string
    {
        return 'https://sourceforge.net/u/{user}/profile/';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://sourceforge.net/rest/u/{$target}/profile";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 15;
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            // Browser-looking clients trigger SourceForge's Cloudflare path;
            // ordinary API clients reach this public JSON endpoint directly.
            'User-Agent' => 'curl/8.0.1',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            if ($this->pageTitle($response->body()) === 'Page not found - SourceForge.net') {
                return ['Available', ''];
            }

            return ['Error', 'Unexpected SourceForge 404 response'];
        }

        $profile = $response->json();
        if ($status === 200 && strcasecmp((string) ($profile['username'] ?? ''), $target) === 0) {
            return ['Taken', ''];
        }

        return ['Error', 'SourceForge profile confirmation not found'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $fallback = parent::buildStructuredMetadata($response, $target, $status);
        if (! in_array($status, ['Taken', 'Found'], true)) {
            return $fallback;
        }

        $profile = $response->json();
        if (! is_array($profile) || strcasecmp((string) ($profile['username'] ?? ''), $target) !== 0) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['api_json']),
            'evidence' => $this->mergeSources($fallback['evidence'] ?? [], ['profile_url', 'api_json']),
            'status_detail' => 'found',
            'platform' => $this->key(),
        ];

        if (! empty($profile['name'])) {
            $metadata['display_name'] = trim((string) $profile['name']);
            $metadata['evidence'] = $this->mergeSources($metadata['evidence'], ['display_name']);
        }

        if (! empty($profile['joined'])) {
            $metadata['created_at'] = (string) $profile['joined'];
            $metadata['evidence'] = $this->mergeSources($metadata['evidence'], ['created_at']);
        }

        $projects = is_array($profile['projects'] ?? null) ? $profile['projects'] : [];
        if ($projects !== []) {
            $metadata['projects_count'] = count($projects);
            $metadata['projects'] = array_values(array_filter(array_map(
                static fn (mixed $project): ?string => is_array($project) && is_string($project['name'] ?? null) ? $project['name'] : null,
                $projects,
            )));
            $metadata['evidence'] = $this->mergeSources($metadata['evidence'], ['projects_count']);
        }

        $location = array_values(array_filter([
            data_get($profile, 'localization.city'),
            data_get($profile, 'localization.country'),
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
        if ($location !== []) {
            $metadata['location'] = implode(', ', $location);
        }

        $merged = array_replace($fallback, $metadata);
        $merged['observed_metadata_level'] = 4;

        return $merged;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (! in_array($status, ['Taken', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);
        $summary = [];

        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Joined'] = $metadata['created_at'];
        }
        if (is_int($metadata['projects_count'] ?? null)) {
            $summary['Projects'] = $metadata['projects_count'];
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @param  array<int, string>  $newSources
     * @return array<int, string>
     */
    private function mergeSources(mixed $existing, array $newSources): array
    {
        $sources = is_array($existing) ? $existing : [];
        $merged = [];

        foreach (array_merge($sources, $newSources) as $source) {
            if (! is_string($source) || trim($source) === '') {
                continue;
            }

            $merged[] = trim($source);
        }

        return array_values(array_unique($merged));
    }

    private function pageTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return '';
        }

        return trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5),
        ));
    }
}
