<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/community/wikipedia.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class WikipediaValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'wikipedia';
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
        return 'Wikipedia';
    }

    public function siteUrl(): string
    {
        return 'https://en.wikipedia.org/wiki/User:{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://en.wikipedia.org/w/api.php?action=query&format=json&list=users&ususers={$target}&usprop=editcount|registration|gender&formatversion=2";
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
    if ($response->status() !== 200) {
        return ['Error', 'Unexpected status: ' . $response->status()];
    }

    $data = $response->json();
    $users = data_get($data, 'query.users', []);
    if (!is_array($users) || $users === []) {
        return ['Error', 'Invalid API response format'];
    }

    $userData = $users[0] ?? [];
    if (is_array($userData) && array_key_exists('missing', $userData)) {
        return ['Available', ''];
    }

    return ['Taken', ''];
}

    /**
     * @return array<string, mixed>
     */
    protected function buildStructuredMetadata(Response $response, string $target, string $status): array
    {
        if (!in_array($status, ['Taken', 'Found'], true)) {
            return [];
        }

        $user = $this->extractUserData($response);
        if ($user === null) {
            return [];
        }

        $username = $this->stringValue($user['name'] ?? null) ?? $target;
        $editCount = $this->intValue($user['editcount'] ?? null);

        $metadata = [
            'display_name' => $username,
            'username' => $username,
            'sources' => ['api_json'],
        ];

        $userId = $this->intValue($user['userid'] ?? null);
        if ($userId !== null) {
            $metadata['wikipedia_user_id'] = $userId;
        }

        if ($editCount !== null) {
            $metadata['edit_count'] = $editCount;
            $metadata['posts_count'] = $editCount;
        }

        $createdAt = $this->normalizeDateValue($user['registration'] ?? null);
        if ($createdAt !== null) {
            $metadata['created_at'] = $createdAt;
        }

        $gender = $this->stringValue($user['gender'] ?? null);
        if ($gender !== null) {
            $metadata['gender'] = $gender;
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

        if (isset($metadata['wikipedia_user_id'])) {
            $summary['User ID'] = (string) $metadata['wikipedia_user_id'];
        }
        if (isset($metadata['edit_count'])) {
            $summary['Edit Count'] = (string) $metadata['edit_count'];
        }
        if (is_string($metadata['gender'] ?? null) && $metadata['gender'] !== '') {
            $summary['Gender'] = $metadata['gender'];
        }

        return $this->metadataSummary($summary);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractUserData(Response $response): ?array
    {
        $data = $response->json();
        $users = data_get($data, 'query.users', []);
        if (!is_array($users) || $users === []) {
            return null;
        }

        $user = $users[0] ?? null;

        return is_array($user) ? $user : null;
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
