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
        return 'https://sourceforge.net/u';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://sourceforge.net/u/{$target}/";
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
            // No connector-specific headers inferred.
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = strtolower($response->body());

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        $availableStatuses = [404];
        $takenStatuses = [200];
        $availableIndicators = [];
        $takenIndicators = [];

        if ($this->mode() === 'username') {
            if (in_array($status, $availableStatuses, true)) {
                return ['Available', ''];
            }
            if (in_array($status, $takenStatuses, true)) {
                return ['Taken', ''];
            }
            foreach ($takenIndicators as $needle) {
                if ($needle !== '' && str_contains($body, $needle)) {
                    return ['Taken', ''];
                }
            }
            foreach ($availableIndicators as $needle) {
                if ($needle !== '' && str_contains($body, $needle)) {
                    return ['Available', ''];
                }
            }

            return ['Error', $this->key() . ': indeterminate username response (HTTP ' . $status . ')'];
        }

        if (in_array($status, $takenStatuses, true)) {
            return ['Registered', ''];
        }
        if (in_array($status, $availableStatuses, true)) {
            return ['Not Registered', ''];
        }
        foreach ($takenIndicators as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Registered', ''];
            }
        }
        foreach ($availableIndicators as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Not Registered', ''];
            }
        }

        return ['Error', $this->key() . ': indeterminate email response (HTTP ' . $status . ')'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        $fallback = parent::buildStructuredMetadata($response, $target, $status);
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return $fallback;
        }

        $html = $response->body();
        $profileUrl = $this->requestUrl($target);
        $displayName = $this->extractHeading($html);
        $joinedAt = $this->extractJoinedAt($html);
        $projectUrls = $this->extractProjectUrls($html);
        $projectsCount = count($projectUrls);

        if ($displayName === null && $joinedAt === null && $projectsCount === 0) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['html_profile']),
            'evidence' => $this->mergeSources($fallback['evidence'] ?? [], ['profile_url', 'html_profile']),
            'status_detail' => 'found',
            'platform' => $this->key(),
        ];

        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
            $metadata['evidence'] = $this->mergeSources($metadata['evidence'], ['display_name']);
        }

        if ($joinedAt !== null) {
            $metadata['created_at'] = $joinedAt;
            $metadata['evidence'] = $this->mergeSources($metadata['evidence'], ['created_at']);
        }

        if ($projectsCount > 0) {
            $metadata['projects_count'] = $projectsCount;
            $metadata['external_links'] = $projectUrls;
            $metadata['evidence'] = $this->mergeSources($metadata['evidence'], ['projects_count', 'external_links']);
        }

        $merged = array_replace($fallback, $metadata);
        $merged['observed_metadata_level'] = 4;

        return $merged;
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
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

    private function extractHeading(string $html): ?string
    {
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $matches) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));

        return $value !== '' ? $value : null;
    }

    private function extractJoinedAt(string $html): ?string
    {
        if (preg_match('/<dt>\s*Joined:\s*<\/dt>\s*<dd>\s*([^<]+)\s*<\/dd>/i', $html, $matches) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));
        if ($value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractProjectUrls(string $html): array
    {
        if (preg_match('/<h3[^>]*>\s*Projects\s*<\/h3>(.*?)<h3[^>]*>\s*Personal Tools\s*<\/h3>/is', $html, $sectionMatches) !== 1) {
            return [];
        }

        if (preg_match_all('/href="(\/p\/[^"]+)"/i', $sectionMatches[1], $linkMatches) < 1) {
            return [];
        }

        $links = [];
        foreach ($linkMatches[1] as $path) {
            $normalized = 'https://sourceforge.net' . trim($path);
            $links[] = rtrim($normalized, '/') . '/';
        }

        return array_values(array_unique($links));
    }

    /**
     * @param mixed $existing
     * @param array<int, string> $newSources
     * @return array<int, string>
     */
    private function mergeSources(mixed $existing, array $newSources): array
    {
        $sources = is_array($existing) ? $existing : [];
        $merged = [];

        foreach (array_merge($sources, $newSources) as $source) {
            if (!is_string($source) || trim($source) === '') {
                continue;
            }

            $merged[] = trim($source);
        }

        return array_values(array_unique($merged));
    }
}
