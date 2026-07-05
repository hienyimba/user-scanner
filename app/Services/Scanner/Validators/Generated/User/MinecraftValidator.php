<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MinecraftValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'minecraft';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Minecraft';
    }

    public function siteUrl(): string
    {
        return 'https://namemc.com/profile/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.mojang.com/minecraft/profile/lookup/name/{$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return match ($response->status()) {
            204, 404 => ['Available', ''],
            200 => ['Taken', ''],
            401, 403, 429 => ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $response->status() . ')'],
            default => ['Error', $this->key() . ': indeterminate username response (HTTP ' . $response->status() . ')'],
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
            'username' => trim((string) ($data['name'] ?? $target)),
            'sources' => ['api_json'],
        ];

        if (!empty($data['id'])) {
            $metadata['uuid'] = (string) $data['id'];
            $metadata['avatar_url'] = 'https://crafatar.com/avatars/' . $metadata['uuid'] . '?size=256&overlay';
        }

        if (($metadata['username'] ?? '') !== '') {
            $metadata['display_name'] = $metadata['username'];
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

        if (is_string($metadata['uuid'] ?? null) && $metadata['uuid'] !== '') {
            $summary['UUID'] = $metadata['uuid'];
        }
        if (is_string($metadata['username'] ?? null) && $metadata['username'] !== '') {
            $summary['Username'] = $metadata['username'];
        }

        return $this->metadataSummary($summary);
    }
}
