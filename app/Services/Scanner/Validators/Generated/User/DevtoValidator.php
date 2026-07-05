<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DevtoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'devto';
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
        return 'Devto';
    }

    public function siteUrl(): string
    {
        return 'https://dev.to/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://dev.to/api/users/by_username';
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
        return [];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'url' => $target,
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            200 => ['Taken', ''],
            404 => ['Available', ''],
            default => ['Error', 'Unexpected status: ' . $response->status()],
        };
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

        $displayName = trim((string) ($data['name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $bio = trim((string) ($data['summary'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $joinedAt = trim((string) ($data['joined_at'] ?? ''));
        if ($joinedAt !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($joinedAt))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $joinedAt;
            }
        }

        $website = trim((string) ($data['website_url'] ?? ''));
        if ($website !== '') {
            $metadata['website_url'] = $website;
            $metadata['external_links'] = [$website];
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
        if (is_string($metadata['website_url'] ?? null) && $metadata['website_url'] !== '') {
            $summary['Website'] = $metadata['website_url'];
        }

        return $this->metadataSummary($summary);
    }
}
