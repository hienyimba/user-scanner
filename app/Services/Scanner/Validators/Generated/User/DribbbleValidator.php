<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DribbbleValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'dribbble';
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
        return 'Dribbble';
    }

    public function siteUrl(): string
    {
        return 'https://dribbble.com/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://dribbble.com/{$target}/about";
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
        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        if ($response->status() === 404) {
            return ['Available', ''];
        }

        if ($response->status() === 200) {
            $html = $response->body();
            $showUrl = 'https://dribbble.com/' . $target;

            $foundUrl = '';
            if (preg_match('/<link rel="canonical" href="([^"]+)"/i', $html, $matches) === 1) {
                $foundUrl = trim($matches[1]);
            } elseif (preg_match('/<meta property="og:url" content="([^"]+)"/i', $html, $matches) === 1) {
                $foundUrl = trim($matches[1]);
            }

            if ($foundUrl !== '' && rtrim(strtolower($foundUrl), '/') === rtrim(strtolower($showUrl), '/')) {
                return ['Taken', ''];
            }

            return ['Available', ''];
        }

        return ['Error', 'Unexpected status code: ' . $response->status()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $html = $response->body();
        $metadata = [
            'username' => $target,
            'sources' => ['profile_html'],
        ];

        if (preg_match('/<h1 class="masthead-profile-name">([^<]+)<\/h1>/', $html, $matches) === 1) {
            $name = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($name !== '') {
                $metadata['display_name'] = $name;
            }
        }

        if (preg_match('/<p class="bio-text">([^<]+)<\/p>/', $html, $matches) === 1) {
            $bio = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($bio !== '') {
                $metadata['bio'] = $bio;
            }
        }

        if (preg_match('/<p class="masthead-profile-locality"><a[^>]*>([^<]+)<\/a>/', $html, $matches) === 1
            || preg_match('/class="location[^"]*">([^<]+)<\/span>/i', $html, $matches) === 1) {
            $location = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($location !== '') {
                $metadata['location'] = $location;
            }
        }

        foreach ([
            'followers' => '/([0-9.,kKmM]+)\s*<\/span>\s*<span class="meta">followers/i',
            'following' => '/([0-9.,kKmM]+)\s*<\/span>\s*<span class="meta">following/i',
        ] as $key => $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                $value = $this->normalizeHumanMetric($matches[1]);
                if ($value !== null) {
                    $metadata[$key] = $value;
                }
            }
        }

        if (preg_match('/Member since ([^<]+)<\/span>/i', $html, $matches) === 1) {
            $memberSince = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
            if ($memberSince !== '') {
                try {
                    $metadata['created_at'] = (new \DateTimeImmutable($memberSince))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->format(DATE_ATOM);
                } catch (\Throwable) {
                    $metadata['created_at'] = $memberSince;
                }
            }
        }

        return $metadata;
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
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (isset($metadata['following'])) {
            $summary['Following'] = (string) $metadata['following'];
        }

        return $this->metadataSummary($summary);
    }

    private function normalizeHumanMetric(string $value): ?int
    {
        $normalized = strtolower(trim(str_replace(',', '', $value)));
        if ($normalized === '') {
            return null;
        }

        $multiplier = 1;
        if (str_ends_with($normalized, 'k')) {
            $multiplier = 1000;
            $normalized = substr($normalized, 0, -1);
        } elseif (str_ends_with($normalized, 'm')) {
            $multiplier = 1000000;
            $normalized = substr($normalized, 0, -1);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return (int) round(((float) $normalized) * $multiplier);
    }
}
