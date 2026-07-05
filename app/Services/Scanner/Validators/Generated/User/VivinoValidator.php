<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/vivino.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class VivinoValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'vivino';
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
        return 'Vivino';
    }

    public function siteUrl(): string
    {
        return 'https://www.vivino.com/users/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.vivino.com/users/{$target}";
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
        if (!is_array($data) || !array_key_exists('id', $data)) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $metadata['vivino_id'] = is_numeric($data['id']) ? (int) $data['id'] : (string) $data['id'];

        $alias = trim((string) ($data['alias'] ?? ''));
        if ($alias !== '') {
            $metadata['display_name'] = $alias;
            $metadata['alias'] = $alias;
        }

        $seoName = trim((string) ($data['seo_name'] ?? ''));
        if ($seoName !== '') {
            $metadata['seo_name'] = $seoName;
        }

        if (array_key_exists('is_premium', $data)) {
            $metadata['is_premium'] = (bool) $data['is_premium'];
        }

        $image = data_get($data, 'image.location');
        if (is_string($image) && trim($image) !== '') {
            $image = trim($image);
            if (str_starts_with($image, '//')) {
                $image = 'https:' . $image;
            }
            $metadata['avatar_url'] = $image;
        }

        $language = trim((string) ($data['language'] ?? ''));
        if ($language !== '') {
            $metadata['language'] = $language;
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

        if (isset($metadata['vivino_id'])) {
            $summary['ID'] = (string) $metadata['vivino_id'];
        }
        if (is_string($metadata['alias'] ?? null) && $metadata['alias'] !== '') {
            $summary['Alias'] = $metadata['alias'];
        }
        if (is_string($metadata['seo_name'] ?? null) && $metadata['seo_name'] !== '') {
            $summary['SEO Name'] = $metadata['seo_name'];
        }
        if (is_string($metadata['language'] ?? null) && $metadata['language'] !== '') {
            $summary['Language'] = $metadata['language'];
        }
        if (array_key_exists('is_premium', $metadata)) {
            $summary['Premium'] = $metadata['is_premium'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }
}
