<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/creator/fansly.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FanslyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'fansly';
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
        return 'Fansly';
    }

    public function siteUrl(): string
    {
        return 'https://fansly.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://apiv2.fansly.com/api/v1/account?usernames={$target}";
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
            $entries = data_get($data, 'response', []);
            if (data_get($data, 'success') === true && is_array($entries) && $entries !== []) {
                return ['Taken', ''];
            }

            if (data_get($data, 'success') === true && is_array($entries)) {
                return ['Available', ''];
            }
        }

        if ($status === 404) {
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

        $entry = data_get($response->json(), 'response.0');
        if (!is_array($entry)) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        if (isset($entry['id']) && is_numeric($entry['id'])) {
            $metadata['fansly_id'] = (int) $entry['id'];
        }

        $displayName = trim((string) ($entry['displayName'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        if (isset($entry['followCount']) && is_numeric($entry['followCount'])) {
            $metadata['followers'] = (int) $entry['followCount'];
        }

        $timelineStats = $entry['timelineStats'] ?? null;
        if (is_array($timelineStats)) {
            if (isset($timelineStats['imageCount']) && is_numeric($timelineStats['imageCount'])) {
                $metadata['images'] = (int) $timelineStats['imageCount'];
            }
            if (isset($timelineStats['videoCount']) && is_numeric($timelineStats['videoCount'])) {
                $metadata['videos'] = (int) $timelineStats['videoCount'];
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

        if (isset($metadata['fansly_id'])) {
            $summary['ID'] = (string) $metadata['fansly_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (isset($metadata['followers'])) {
            $summary['Followers'] = (string) $metadata['followers'];
        }
        if (isset($metadata['images'])) {
            $summary['Images'] = (string) $metadata['images'];
        }
        if (isset($metadata['videos'])) {
            $summary['Videos'] = (string) $metadata['videos'];
        }

        return $this->metadataSummary($summary);
    }
}
