<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/finance/niftygateway.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class NiftygatewayValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'niftygateway';
    }

    public function category(): string
    {
        return 'finance';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Niftygateway';
    }

    public function siteUrl(): string
    {
        return 'https://niftygateway.com/profile/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://api.niftygateway.com/user/profile-and-offchain-nifties-by-url/?profile_url={$target}";
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

        if ($status === 200) {
            $data = $response->json();
            if (data_get($data, 'didSucceed') === true && data_get($data, 'userProfileAndNifties.id') !== null) {
                return ['Taken', ''];
            }

            if (data_get($data, 'didSucceed') === true) {
                return ['Available', ''];
            }
        }

        if ($status === 400 || $status === 404 || str_contains($body, 'not_found')) {
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

        $user = data_get($response->json(), 'userProfileAndNifties');
        if (!is_array($user)) {
            return [];
        }

        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        if (isset($user['id']) && is_numeric($user['id'])) {
            $metadata['niftygateway_id'] = (int) $user['id'];
        } elseif (isset($user['id'])) {
            $metadata['niftygateway_id'] = (string) $user['id'];
        }

        if (isset($user['user_id']) && is_numeric($user['user_id'])) {
            $metadata['user_id'] = (int) $user['user_id'];
        } elseif (isset($user['user_id'])) {
            $metadata['user_id'] = (string) $user['user_id'];
        }

        $name = trim((string) ($user['name'] ?? ''));
        if ($name !== '') {
            $metadata['display_name'] = $name;
        }

        $bio = trim((string) ($user['bio'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
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

        if (isset($metadata['niftygateway_id'])) {
            $summary['ID'] = (string) $metadata['niftygateway_id'];
        }
        if (isset($metadata['user_id'])) {
            $summary['User ID'] = (string) $metadata['user_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }

        return $this->metadataSummary($summary);
    }
}
