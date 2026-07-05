<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/paragraph.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ParagraphValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'paragraph';
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
        return 'Paragraph';
    }

    public function siteUrl(): string
    {
        return 'https://paragraph.com/@{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://paragraph.com/api/blogs/@{$target}";
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
            if (is_array($data) && array_key_exists('id', $data)) {
                return ['Taken', ''];
            }
        }

        return ['Available', ''];
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
            $metadata['paragraph_id'] = (int) $data['id'];
        } elseif (isset($data['id'])) {
            $metadata['paragraph_id'] = (string) $data['id'];
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $metadata['display_name'] = $name;
        }

        $createdAt = trim((string) ($data['createdAt'] ?? ''));
        if ($createdAt !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($createdAt))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $createdAt;
            }
        }

        if (isset($data['userId']) && is_numeric($data['userId'])) {
            $metadata['user_id'] = (int) $data['userId'];
        } elseif (isset($data['userId'])) {
            $metadata['user_id'] = (string) $data['userId'];
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

        if (isset($metadata['paragraph_id'])) {
            $summary['ID'] = (string) $metadata['paragraph_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created'] = $metadata['created_at'];
        }
        if (isset($metadata['user_id'])) {
            $summary['User ID'] = (string) $metadata['user_id'];
        }

        return $this->metadataSummary($summary);
    }
}
