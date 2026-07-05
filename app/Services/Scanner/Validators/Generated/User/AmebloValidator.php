<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AmebloValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'ameblo'; }
    public function category(): string { return 'creator'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Ameblo'; }
    public function siteUrl(): string { return 'https://ameblo.jp/{user}'; }
    protected function requestUrl(string $target): string { return "https://ameblo.jp/{$target}"; }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            404 => ['Available', ''],
            200 => ['Taken', ''],
            default => ['Error', 'HTTP ' . $response->status()],
        };
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

        $profileTitle = $this->extractProfileTitle($response->body());
        $displayName = $this->extractDisplayName($profileTitle);
        if ($displayName === null) {
            return $fallback;
        }

        $metadata = [
            'username' => $target,
            'display_name' => $displayName,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html']),
        ];

        if ($profileTitle !== null) {
            $metadata['profile_title'] = $profileTitle;
        }

        return array_replace($fallback, $metadata);
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

        return $this->metadataSummary($summary);
    }

    private function extractProfileTitle(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $title !== '' ? $title : null;
    }

    private function extractDisplayName(?string $title): ?string
    {
        if ($title === null) {
            return null;
        }

        $displayName = preg_replace('/\s*(?:プロフィール|ãƒ—ãƒ­ãƒ•ã‚£ãƒ¼ãƒ«)\s*/u', '', $title);
        if (!is_string($displayName)) {
            return null;
        }

        $displayName = trim($displayName);

        return $displayName !== '' ? $displayName : null;
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
