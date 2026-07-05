<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DiscogsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'discogs';
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
        return 'Discogs';
    }

    public function siteUrl(): string
    {
        return 'https://www.discogs.com/user/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.discogs.com/users/{$target}";
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
        $availableIndicators = ['\\"message\\": \\"user does not exist or may have been deleted.\\'];
        $takenIndicators = ['\\"id\\":'];

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

        if (isset($data['id']) && is_numeric($data['id'])) {
            $metadata['discogs_id'] = (int) $data['id'];
        }

        $displayName = trim((string) ($data['name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $bio = trim((string) ($data['profile'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $joined = trim((string) ($data['registered'] ?? ''));
        if ($joined !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($joined))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $joined;
            }
        }

        $website = trim((string) ($data['home_page'] ?? ''));
        if ($website !== '') {
            $metadata['website_url'] = $website;
            $metadata['external_links'] = [$website];
        }

        $avatar = trim((string) ($data['avatar_url'] ?? ''));
        if ($avatar !== '') {
            $metadata['avatar_url'] = $avatar;
        }

        foreach ([
            'releases_contributed' => 'releases_contributed',
            'releases_rated' => 'releases_rated',
            'num_lists' => 'lists',
            'num_collection' => 'collection_items',
            'num_wantlist' => 'wantlist_items',
        ] as $sourceKey => $targetKey) {
            if (isset($data[$sourceKey]) && is_numeric($data[$sourceKey])) {
                $metadata[$targetKey] = (int) $data[$sourceKey];
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
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (isset($metadata['releases_contributed'])) {
            $summary['Releases Contributed'] = (string) $metadata['releases_contributed'];
        }
        if (isset($metadata['collection_items'])) {
            $summary['Collection Items'] = (string) $metadata['collection_items'];
        }

        return $this->metadataSummary($summary);
    }
}
