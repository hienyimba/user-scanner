<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BandlabValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bandlab';
    }

    public function category(): string
    {
        return 'music';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Bandlab';
    }

    public function siteUrl(): string
    {
        return 'https://www.bandlab.com/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.bandlab.com/api/v1.3/users/{$target}";
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
        $availableIndicators = ['couldn\'t find any matching element'];
        $takenIndicators = ['about'];

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
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $bandlabId = $this->intValue($data['id'] ?? null);
        if ($bandlabId !== null) {
            $metadata['bandlab_id'] = $bandlabId;
        }

        $displayName = $this->stringValue($data['name'] ?? null);
        if ($displayName !== null) {
            $metadata['display_name'] = $displayName;
        }

        $bio = $this->stringValue($data['about'] ?? null);
        if ($bio !== null) {
            $metadata['bio'] = $bio;
        }

        $location = $this->stringValue($data['place'] ?? null);
        if ($location !== null) {
            $metadata['location'] = $location;
        }

        $createdAt = $this->normalizeDateValue($data['createdOn'] ?? null);
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $avatarUrl = $this->stringValue(data_get($data, 'picture.url'));
        if ($avatarUrl !== null) {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $followers = $this->intValue(data_get($data, 'counters.followers'));
        if ($followers !== null) {
            $metadata['followers'] = $followers;
        }

        $following = $this->intValue(data_get($data, 'counters.following'));
        if ($following !== null) {
            $metadata['following'] = $following;
        }

        $plays = $this->intValue(data_get($data, 'counters.plays'));
        if ($plays !== null) {
            $metadata['plays_count'] = $plays;
        }

        $bands = $this->intValue(data_get($data, 'counters.bands'));
        if ($bands !== null) {
            $metadata['bands_count'] = $bands;
            $metadata['posts_count'] = $bands;
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
        if (isset($metadata['plays_count'])) {
            $summary['Plays'] = (string) $metadata['plays_count'];
        }
        if (isset($metadata['bands_count'])) {
            $summary['Bands'] = (string) $metadata['bands_count'];
        }

        return $this->metadataSummary($summary);
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        $date = $this->stringValue($value);
        if ($date === null) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($date))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
    }
}
