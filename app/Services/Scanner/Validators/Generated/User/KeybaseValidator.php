<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/social/keybase.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class KeybaseValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'keybase';
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
        return 'Keybase';
    }

    public function siteUrl(): string
    {
        return 'https://keybase.io/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://keybase.io/_/api/1.0/user/lookup.json?usernames={$target}";
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
        if ($response->status() === 200) {
            $data = $response->json();
            $them = data_get($data, 'them', []);
            if (is_array($them) && ($them[0] ?? null) !== null) {
                return ['Taken', ''];
            }
            if (is_array($them)) {
                return ['Available', ''];
            }
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

        $user = data_get($response->json(), 'them.0');
        if (!is_array($user)) {
            return [];
        }

        $username = trim((string) data_get($user, 'basics.username', ''));
        if ($username === '') {
            $username = $target;
        }

        $metadata = [
            'username' => $username,
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) data_get($user, 'profile.full_name', ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $avatarUrl = trim((string) data_get($user, 'pictures.primary.url', ''));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = $avatarUrl;
        }

        $bio = trim((string) data_get($user, 'profile.bio', ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $location = trim((string) data_get($user, 'profile.location', ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $websiteUrl = trim((string) data_get($user, 'profile.website', ''));
        if ($websiteUrl !== '') {
            $metadata['website_url'] = $websiteUrl;
            $metadata['external_links'] = [$websiteUrl];
        }

        $proofLinks = [];
        $proofs = data_get($user, 'proofs_summary.all', []);
        if (is_array($proofs)) {
            foreach ($proofs as $proof) {
                if (!is_array($proof)) {
                    continue;
                }

                foreach (['proof_url', 'service_url', 'human_url'] as $key) {
                    $value = trim((string) ($proof[$key] ?? ''));
                    if ($value !== '' && str_starts_with($value, 'http')) {
                        $proofLinks[] = $value;
                    }
                }
            }
        }

        if ($proofLinks !== []) {
            $metadata['external_links'] = array_values(array_unique(array_merge($metadata['external_links'] ?? [], $proofLinks)));
            $metadata['proof_count'] = count($proofLinks);
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
        if (is_string($metadata['username'] ?? null) && $metadata['username'] !== '') {
            $summary['Username'] = $metadata['username'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['website_url'] ?? null) && $metadata['website_url'] !== '') {
            $summary['Website'] = $metadata['website_url'];
        }
        if (isset($metadata['proof_count'])) {
            $summary['Proofs'] = (string) $metadata['proof_count'];
        }

        return $this->metadataSummary($summary);
    }
}
