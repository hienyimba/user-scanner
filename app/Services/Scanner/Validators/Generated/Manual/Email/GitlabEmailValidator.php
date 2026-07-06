<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Manual\Email;

use Illuminate\Http\Client\PendingRequest;

final class GitlabEmailValidator extends AbstractGravatarLinkedEmailValidator
{
    public function key(): string
    {
        return 'gitlab';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function siteName(): string
    {
        return 'Gitlab';
    }

    public function siteUrl(): string
    {
        return 'https://gitlab.com';
    }

    /**
     * @return array<int, string>
     */
    protected function profileHosts(): array
    {
        return ['gitlab.com'];
    }

    protected function successConfidence(): float
    {
        return 0.97;
    }

    /**
     * @param array<string, mixed> $gravatarEntry
     * @return array{metadata: array<string, mixed>, confidence?: float}
     */
    protected function buildLinkedProfileMetadata(PendingRequest $request, string $profileUrl, array $gravatarEntry): array
    {
        $username = $this->usernameFromProfileUrl($profileUrl);
        if ($username === null) {
            return [
                'metadata' => [
                    'sources' => ['gravatar_profile', 'public_profile_link'],
                ],
            ];
        }

        $response = $request->withHeaders([
            'Accept' => 'application/json, text/plain, */*',
        ])->get('https://gitlab.com/api/v4/users', [
            'username' => $username,
        ]);

        if ($response->status() !== 200 || !is_array($response->json()) || !is_array($response->json()[0] ?? null)) {
            return [
                'metadata' => [
                    'username' => $username,
                    'sources' => ['gravatar_profile', 'public_profile_link'],
                    'linked_profile_http_status' => $response->status(),
                ],
            ];
        }

        $user = $response->json()[0];
        $metadata = [
            'username' => trim((string) ($user['username'] ?? $username)),
            'sources' => ['api_json', 'gravatar_profile', 'public_profile_link', 'gitlab_public_api'],
        ];

        $id = $user['id'] ?? null;
        if (is_numeric($id)) {
            $metadata['gitlab_id'] = (int) $id;
            $metadata['user_id'] = (int) $id;
        } elseif (is_scalar($id) && trim((string) $id) !== '') {
            $metadata['gitlab_id'] = trim((string) $id);
            $metadata['user_id'] = trim((string) $id);
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

        return [
            'metadata' => $metadata,
            'confidence' => $this->successConfidence(),
        ];
    }
}
