<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class YoutubeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'youtube';
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
        return 'Youtube';
    }

    public function siteUrl(): string
    {
        return 'https://youtube.com/@{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://m.youtube.com/@{$target}";
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
                if (str_contains($body, 'this channel does not exist') || str_contains($body, '404 not found')) {
                    return ['Available', ''];
                }

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

        $metadata = $fallback;
        $metadata['username'] = $target;
        $metadata['sources'] = $this->mergeSources($metadata['sources'] ?? [], ['html_hydration']);

        $hydration = $this->extractInitialData($response->body());
        if (is_array($hydration)) {
            $channel = data_get($hydration, 'metadata.channelMetadataRenderer', []);
            if (is_array($channel)) {
                $displayName = $this->stringValue($channel['title'] ?? null);
                if ($displayName !== null) {
                    $metadata['display_name'] = $displayName;
                }

                $bio = $this->stringValue($channel['description'] ?? null);
                if ($bio !== null) {
                    $metadata['bio'] = $bio;
                }

                $channelId = $this->stringValue($channel['externalId'] ?? null);
                if ($channelId !== null) {
                    $metadata['youtube_channel_id'] = $channelId;
                }

                $channelUrl = $this->stringValue($channel['vanityChannelUrl'] ?? null);
                if ($channelUrl !== null) {
                    $metadata['channel_url'] = $channelUrl;
                    $metadata['external_links'] = $this->mergeSources($metadata['external_links'] ?? [], [$channelUrl]);
                }

                if (array_key_exists('isFamilySafe', $channel) && is_bool($channel['isFamilySafe'])) {
                    $metadata['is_family_safe'] = (bool) $channel['isFamilySafe'];
                }

                $keywords = $this->stringValue($channel['keywords'] ?? null);
                if ($keywords !== null) {
                    $metadata['keywords'] = $keywords;
                }

                $avatarUrl = $this->extractThumbnailUrl($channel['avatar']['thumbnails'] ?? null);
                if ($avatarUrl !== null) {
                    $metadata['avatar_url'] = $avatarUrl;
                }
            }
        }

        $subscriberText = $this->extractSubscriberText($response->body());
        if ($subscriberText !== null) {
            $profileExtractor = app(\App\Services\Scanner\ProfileMetadataExtractor::class);
            $followers = $profileExtractor->extractMetricValue(str_ireplace(' subscribers', '', $subscriberText));
            if (is_int($followers) || is_float($followers)) {
                $metadata['followers'] = $followers;
            } else {
                $metadata['subscribers_text'] = $subscriberText;
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
        if (isset($metadata['followers']) && (is_int($metadata['followers']) || is_float($metadata['followers']))) {
            $summary['Subscribers'] = (string) $metadata['followers'];
        } elseif (is_string($metadata['subscribers_text'] ?? null) && $metadata['subscribers_text'] !== '') {
            $summary['Subscribers'] = $metadata['subscribers_text'];
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractInitialData(string $html): ?array
    {
        if (preg_match('/var ytInitialData = (\{.*?\});/s', $html, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[1], true);

        return is_array($decoded) ? $decoded : null;
    }

    private function extractSubscriberText(string $html): ?string
    {
        if (preg_match('/"content":"([0-9.]+[A-Z]?\s+subscribers)"/i', $html, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    /**
     * @param mixed $thumbnails
     */
    private function extractThumbnailUrl(mixed $thumbnails): ?string
    {
        if (!is_array($thumbnails) || $thumbnails === []) {
            return null;
        }

        foreach ($thumbnails as $thumbnail) {
            if (!is_array($thumbnail)) {
                continue;
            }

            $url = $this->stringValue($thumbnail['url'] ?? null);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    /**
     * @param mixed $existing
     * @param array<int, string> $newValues
     * @return array<int, string>
     */
    private function mergeSources(mixed $existing, array $newValues): array
    {
        $values = is_array($existing) ? $existing : [];
        $merged = [];

        foreach (array_merge($values, $newValues) as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $merged[] = trim($value);
        }

        return array_values(array_unique($merged));
    }
}
