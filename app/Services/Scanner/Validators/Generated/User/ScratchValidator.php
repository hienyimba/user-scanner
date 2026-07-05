<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/dev/scratch.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ScratchValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'scratch';
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
        return 'Scratch';
    }

    public function siteUrl(): string
    {
        return 'https://scratch.mit.edu/users/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.scratch.mit.edu/users/{$target}";
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
    $body = $response->body();

    if ($status === 404) {
        return ['Available', ''];
    }

    if ($status === 200) {
        return ['Taken', ''];
    }

    return ['Error', 'Unexpected response body'];
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
            $metadata['scratch_id'] = (int) $data['id'];
        } elseif (isset($data['id'])) {
            $metadata['scratch_id'] = (string) $data['id'];
        }

        $joined = trim((string) data_get($data, 'history.joined', ''));
        if ($joined !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($joined))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $joined;
            }
        }

        $avatar = trim((string) data_get($data, 'profile.images.90x90', ''));
        if ($avatar !== '') {
            $metadata['avatar_url'] = $avatar;
        }

        $country = trim((string) data_get($data, 'profile.country', ''));
        if ($country !== '') {
            $metadata['location'] = $country;
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

        if (isset($metadata['scratch_id'])) {
            $summary['ID'] = (string) $metadata['scratch_id'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Joined'] = $metadata['created_at'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Country'] = $metadata['location'];
        }

        return $this->metadataSummary($summary);
    }
}
