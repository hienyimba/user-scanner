<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ItchIoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'itch_io';
    }

    public function category(): string
    {
        return 'creator';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'ItchIo';
    }

    public function siteUrl(): string
    {
        return 'https://itch.io/profile/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://itch.io/profile/{$target}";
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

    public function check(string $target, array $options = []): ScanResult
    {
        if (strlen($target) < 2 || strlen($target) > 25) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Length must be 2-25 characters.', mode: $this->mode(), key: $this->key());
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $target)) {
            if (preg_match('/[A-Z]/', $target)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Use lowercase letters only.', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Only use lowercase letters, numbers, underscores, and hyphens.', mode: $this->mode(), key: $this->key());
        }

        return parent::check($target, $options);
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

        $title = $this->extractDisplayName($response->body());
        if ($title === null) {
            return $fallback;
        }

        return array_replace($fallback, [
            'username' => $target,
            'display_name' => $title,
            'profile_title' => $title,
            'sources' => $this->mergeSources($fallback['sources'] ?? [], ['profile_html']),
        ]);
    }

    protected function buildExtraMetadata(Response $response, string $target, string $status): string
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return '';
        }

        $metadata = $this->buildStructuredMetadata($response, $target, $status);

        return $this->metadataSummary([
            'Name' => $metadata['display_name'] ?? null,
        ]);
    }

    private function extractDisplayName(string $html): ?string
    {
        if (preg_match('/<title>([^<]+)-\s*itch\.io<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));

        return $title !== '' ? $title : null;
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
