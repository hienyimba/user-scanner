<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class EtoroValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'etoro';
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
        return 'Etoro';
    }

    public function siteUrl(): string
    {
        return 'https://www.etoro.com/people/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.etoro.com/api/logininfo/v1.1/users/{$target}";
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
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Referer' => 'https://www.etoro.com/',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = strtolower($response->body());

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        $availableStatuses = [];
        $takenStatuses = [];
        $availableIndicators = ['errorcode":"notfound'];
        $takenIndicators = ['gcid":'];

        if ($this->mode() === 'username') {
            if (in_array($status, $availableStatuses, true)) {
                return ['Available', ''];
            }
            if (in_array($status, $takenStatuses, true)) {
                return ['Taken', ''];
            }
            foreach ($takenIndicators as $needle) {
                if ($needle !== '' && str_contains($body, $needle)) {
                    return ['Taken', ''];
                }
            }
            foreach ($availableIndicators as $needle) {
                if ($needle !== '' && str_contains($body, $needle)) {
                    return ['Available', ''];
                }
            }

            return ['Error', $this->key() . ': indeterminate username response (HTTP ' . $status . ')'];
        }

        if (in_array($status, $takenStatuses, true)) {
            return ['Registered', ''];
        }
        if (in_array($status, $availableStatuses, true)) {
            return ['Not Registered', ''];
        }
        foreach ($takenIndicators as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Registered', ''];
            }
        }
        foreach ($availableIndicators as $needle) {
            if ($needle !== '' && str_contains($body, $needle)) {
                return ['Not Registered', ''];
            }
        }

        return ['Error', $this->key() . ': indeterminate email response (HTTP ' . $status . ')'];
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

        $nameParts = [];
        foreach (['firstName', 'lastName'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value !== '') {
                $nameParts[] = $value;
            }
        }
        if ($nameParts !== []) {
            $metadata['display_name'] = implode(' ', $nameParts);
        }

        $bio = trim((string) ($data['aboutMe'] ?? ''));
        if ($bio === '') {
            $bio = trim((string) ($data['aboutMeShort'] ?? ''));
        }
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $avatars = $data['avatars'] ?? null;
        if (is_array($avatars) && isset($avatars[0]) && is_array($avatars[0])) {
            $avatarUrl = trim((string) ($avatars[0]['url'] ?? ''));
            if ($avatarUrl !== '') {
                $metadata['avatar_url'] = $avatarUrl;
            }
        }

        if (array_key_exists('isVerified', $data)) {
            $metadata['is_verified'] = (bool) $data['isVerified'];
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
        if (is_string($metadata['avatar_url'] ?? null) && $metadata['avatar_url'] !== '') {
            $summary['Avatar'] = $metadata['avatar_url'];
        }
        if (array_key_exists('is_verified', $metadata)) {
            $summary['Verified'] = $metadata['is_verified'] ? 'Yes' : 'No';
        }

        return $this->metadataSummary($summary);
    }
}
