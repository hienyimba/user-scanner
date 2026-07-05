<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class GitlabValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'gitlab';
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
        return 'Gitlab';
    }

    public function siteUrl(): string
    {
        return 'https://gitlab.com/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://gitlab.com/api/v4/users';
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
        return [
            'Accept' => 'application/json, text/plain, */*',
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'username' => $target,
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected status or response: ' . $response->status()];
        }

        $data = $response->json();
        if (!is_array($data)) {
            return ['Error', 'Unexpected status or response: ' . $response->status()];
        }

        return $data === [] ? ['Available', ''] : ['Taken', ''];
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
        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            return [];
        }

        $user = $data[0];
        $metadata = [
            'username' => trim((string) ($user['username'] ?? $target)),
            'sources' => ['api_json'],
        ];

        $id = $user['id'] ?? null;
        if (is_numeric($id)) {
            $metadata['gitlab_id'] = (int) $id;
        } elseif (is_scalar($id)) {
            $metadata['gitlab_id'] = trim((string) $id);
        }

        $displayName = trim((string) ($user['name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $state = trim((string) ($user['state'] ?? ''));
        if ($state !== '') {
            $metadata['account_state'] = $state;
        }

        $avatarUrl = trim((string) ($user['avatar_url'] ?? ''));
        if ($avatarUrl !== '') {
            $metadata['avatar_url'] = $avatarUrl;
            if (preg_match('#gravatar\.com/avatar/([a-f0-9]{32})#i', $avatarUrl, $matches) === 1) {
                $hash = strtolower($matches[1]);
                $metadata['gravatar_url'] = 'https://gravatar.com/' . $hash;
                $metadata['gravatar_username'] = $metadata['username'];
                $metadata['gravatar_email_md5_hash'] = $hash;
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

        if (isset($metadata['gitlab_id'])) {
            $summary['UID'] = (string) $metadata['gitlab_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Full Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['account_state'] ?? null) && $metadata['account_state'] !== '') {
            $summary['State'] = $metadata['account_state'];
        }

        return $this->metadataSummary($summary);
    }
}
