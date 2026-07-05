<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/omglol.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class OmglolValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'omglol';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Omglol';
    }

    public function siteUrl(): string
    {
        return 'https://omg.lol/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.omg.lol/address/{$target}/info";
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

        $data = data_get($response->json(), 'response');
        if (!is_array($data) || !isset($data['address'])) {
            return [];
        }

        $metadata = [
            'username' => trim((string) ($data['address'] ?? '')) ?: $target,
            'sources' => ['api_json'],
        ];

        $message = trim((string) ($data['message'] ?? ''));
        if ($message !== '') {
            $metadata['bio'] = $message;
            $metadata['message'] = $message;
        }

        $registrationDate = trim((string) data_get($data, 'registration.date', ''));
        if ($registrationDate !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($registrationDate))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $registrationDate;
            }
        }

        $expiration = trim((string) data_get($data, 'registration.expiration', ''));
        if ($expiration !== '') {
            try {
                $metadata['expiration_at'] = (new \DateTimeImmutable($expiration))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['expiration_at'] = $expiration;
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

        if (is_string($metadata['username'] ?? null) && $metadata['username'] !== '') {
            $summary['Address'] = $metadata['username'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Message'] = $metadata['bio'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Registered'] = $metadata['created_at'];
        }
        if (is_string($metadata['expiration_at'] ?? null) && $metadata['expiration_at'] !== '') {
            $summary['Expiration'] = $metadata['expiration_at'];
        }

        return $this->metadataSummary($summary);
    }
}
