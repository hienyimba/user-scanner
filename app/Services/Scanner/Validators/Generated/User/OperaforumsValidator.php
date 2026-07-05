<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/operaforums.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class OperaforumsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'operaforums';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Operaforums';
    }

    public function siteUrl(): string
    {
        return 'https://forums.opera.com/user/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://forums.opera.com/api/user/{$target}";
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

        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', 'Unexpected response status: ' . $status];
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
            'username' => $this->stringValue($data['username'] ?? null) ?? $target,
            'sources' => ['api_json'],
        ];

        $location = $this->stringValue($data['location'] ?? null);
        if ($location !== null) {
            $metadata['location'] = $location;
        }

        $createdAt = $this->normalizeDateValue($data['joindateISO'] ?? null);
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $reputation = $this->intValue($data['reputation'] ?? null);
        if ($reputation !== null) {
            $metadata['reputation'] = $reputation;
        }

        $profileViews = $this->intValue($data['profileviews'] ?? null);
        if ($profileViews !== null) {
            $metadata['profile_views'] = $profileViews;
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

        if (isset($metadata['created_at'])) {
            $summary['Joined At'] = (string) $metadata['created_at'];
        }
        if (isset($metadata['reputation'])) {
            $summary['Reputation'] = (string) $metadata['reputation'];
        }
        if (isset($metadata['profile_views'])) {
            $summary['Profile Views'] = (string) $metadata['profile_views'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
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
