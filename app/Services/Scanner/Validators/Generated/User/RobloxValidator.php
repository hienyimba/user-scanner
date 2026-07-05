<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class RobloxValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'roblox';
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
        return 'Roblox';
    }

    public function siteUrl(): string
    {
        return 'https://roblox.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://users.roblox.com/v1/usernames/users';
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function requestBody(string $target): array
    {
        return [
            'usernames' => [$target],
            'excludeBannedUsers' => false,
        ];
    }

    protected function requestBodyMode(): string
    {
        return 'json';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $first = parent::check($target, $options);
        if (in_array($first->status, ['Taken', 'Found'], true) && is_int($first->metadata['roblox_user_id'] ?? null)) {
            return new ScanResult(
                target: $first->target,
                category: $first->category,
                siteName: $first->siteName,
                url: $first->url,
                status: $first->status,
                reason: $first->reason,
                extra: $first->extra,
                mode: $first->mode,
                key: $first->key,
                metadata: $first->metadata,
                profileUrl: 'https://www.roblox.com/users/' . $first->metadata['roblox_user_id'] . '/profile',
            );
        }

        if ($first->reason !== 'Too many requests') {
            return $first;
        }

        try {
            $request = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get('https://www.roblox.com/user.aspx', ['username' => $target]);

            $status = match ($response->status()) {
                404 => 'Available',
                200, 302 => 'Taken',
                default => 'Error',
            };
            $reason = $status === 'Error' ? 'Invalid status code' : '';

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), $status, $reason, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $data = $response->json();

        if ($response->status() === 429) {
            return ['Error', 'Too many requests'];
        }

        if ($response->status() === 400) {
            $errorCode = $data['errors'][0]['code'] ?? null;
            return match ($errorCode) {
                6 => ['Error', 'Username is too short'],
                5 => ['Error', 'Username was filtered'],
                default => ['Error', 'Invalid username'],
            };
        }

        if ($response->status() === 200 && is_array($data['data'] ?? null) && ($data['data'] ?? []) !== []) {
            return ['Taken', ''];
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

        $data = $response->json('data');
        if (!is_array($data) || !is_array($data[0] ?? null)) {
            return [];
        }

        $entry = $data[0];
        $metadata = [
            'username' => $target,
            'sources' => ['api_json'],
        ];

        $displayName = trim((string) ($entry['displayName'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $userId = $entry['id'] ?? null;
        if (is_numeric($userId)) {
            $metadata['roblox_user_id'] = (int) $userId;
        }

        if (array_key_exists('hasVerifiedBadge', $entry)) {
            $metadata['is_verified'] = (bool) $entry['hasVerifiedBadge'];
        }

        if (isset($metadata['roblox_user_id'])) {
            $detailResponse = $this->fetchUserDetails($metadata['roblox_user_id']);
            if ($detailResponse !== null) {
                $bio = trim((string) ($detailResponse['description'] ?? ''));
                if ($bio !== '') {
                    $metadata['bio'] = $bio;
                }

                $created = trim((string) ($detailResponse['created'] ?? ''));
                if ($created !== '') {
                    $timestamp = strtotime($created);
                    $metadata['created_at'] = $timestamp !== false
                        ? gmdate('Y-m-d\TH:i:s+00:00', $timestamp)
                        : $created;
                }

                if (array_key_exists('isBanned', $detailResponse)) {
                    $metadata['is_banned'] = (bool) $detailResponse['isBanned'];
                }
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

        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Display Name'] = $metadata['display_name'];
        }
        if (is_int($metadata['roblox_user_id'] ?? null)) {
            $summary['UID'] = $metadata['roblox_user_id'];
        }
        if (array_key_exists('is_verified', $metadata)) {
            $summary['Is Verified'] = $metadata['is_verified'] ? 'Yes' : 'No';
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = $metadata['bio'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Created'] = $metadata['created_at'];
        }
        if (array_key_exists('is_banned', $metadata)) {
            $summary['Banned'] = $metadata['is_banned'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUserDetails(int $userId): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->get("https://users.roblox.com/v1/users/{$userId}");

            if ($response->status() !== 200) {
                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
