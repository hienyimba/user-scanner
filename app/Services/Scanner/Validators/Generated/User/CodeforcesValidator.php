<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/codeforces.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class CodeforcesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'codeforces';
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
        return 'Codeforces';
    }

    public function siteUrl(): string
    {
        return 'https://codeforces.com/profile/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://codeforces.com/api/user.info?handles={$target}";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($status === 200) {
            $data = $response->json();
            if (data_get($data, 'status') === 'OK' && data_get($data, 'result.0') !== null) {
                return ['Taken', ''];
            }
        }

        if ($status === 400 || $status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $user = data_get($response->json(), 'result.0');
        if (!is_array($user)) {
            return [];
        }

        $username = trim((string) ($user['handle'] ?? ''));
        if ($username === '') {
            $username = $target;
        }

        $displayName = trim(implode(' ', array_filter([
            trim((string) ($user['firstName'] ?? '')),
            trim((string) ($user['lastName'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));

        $location = trim(implode(', ', array_filter([
            trim((string) ($user['city'] ?? '')),
            trim((string) ($user['country'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));

        $metadata = [
            'username' => $username,
            'sources' => ['api_json'],
        ];

        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $avatarUrl = trim((string) ($user['titlePhoto'] ?? ($user['avatar'] ?? '')));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = $avatarUrl;
        }

        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $rank = trim((string) ($user['rank'] ?? ''));
        if ($rank !== '') {
            $metadata['account_type'] = $rank;
        }

        foreach ([
            'friendOfCount' => 'followers',
            'rating' => 'rating',
            'maxRating' => 'max_rating',
            'contribution' => 'contribution',
        ] as $sourceKey => $metadataKey) {
            if (isset($user[$sourceKey]) && is_numeric($user[$sourceKey])) {
                $metadata[$metadataKey] = (int) $user[$sourceKey];
            }
        }

        $maxRank = trim((string) ($user['maxRank'] ?? ''));
        if ($maxRank !== '') {
            $metadata['max_rank'] = $maxRank;
        }

        $organization = trim((string) ($user['organization'] ?? ''));
        if ($organization !== '') {
            $metadata['organization'] = $organization;
        }

        foreach ([
            'registrationTimeSeconds' => 'created_at',
            'lastOnlineTimeSeconds' => 'last_active_at',
        ] as $sourceKey => $metadataKey) {
            if (isset($user[$sourceKey]) && is_numeric($user[$sourceKey])) {
                try {
                    $metadata[$metadataKey] = (new \DateTimeImmutable('@' . (string) $user[$sourceKey]))
                        ->setTimezone(new \DateTimeZone('UTC'))
                        ->format(DATE_ATOM);
                } catch (\Throwable) {
                    // Ignore invalid timestamps from upstream.
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
        if (is_string($metadata['account_type'] ?? null) && $metadata['account_type'] !== '') {
            $summary['Rank'] = $metadata['account_type'];
        }
        if (isset($metadata['rating'])) {
            $summary['Rating'] = (string) $metadata['rating'];
        }
        if (isset($metadata['max_rating'])) {
            $summary['Max Rating'] = (string) $metadata['max_rating'];
        }
        if (isset($metadata['followers'])) {
            $summary['Friends'] = (string) $metadata['followers'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['organization'] ?? null) && $metadata['organization'] !== '') {
            $summary['Organization'] = $metadata['organization'];
        }

        return $this->metadataSummary($summary);
    }
}
