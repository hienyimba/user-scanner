<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

final class LeetcodeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'leetcode';
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
        return 'Leetcode';
    }

    public function siteUrl(): string
    {
        return 'https://leetcode.com/u/{user}/';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        $query = 'query userPublicProfile($username: String!) { matchedUser(username: $username) { username profile { realName aboutMe userAvatar countryName company school ranking } } }';
        $variables = json_encode(['username' => $target], JSON_THROW_ON_ERROR);

        return 'https://leetcode.com/graphql?query=' . rawurlencode($query) . '&variables=' . rawurlencode($variables);
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
            // No connector-specific headers inferred.
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        if ($response->status() !== 200) {
            return ['Error', 'Unexpected status: ' . $response->status()];
        }

        $data = $response->json();
        if (is_array($data) && isset($data['errors']) && str_contains(json_encode($data['errors']), 'That user does not exist')) {
            return ['Available', ''];
        }

        if (data_get($data, 'data.matchedUser') !== null) {
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

        $user = data_get($response->json(), 'data.matchedUser');
        if (!is_array($user)) {
            return [];
        }

        $profile = $user['profile'] ?? null;
        if (!is_array($profile)) {
            return [];
        }

        $metadata = [
            'username' => trim((string) ($user['username'] ?? '')) ?: $target,
            'sources' => ['api_json'],
        ];

        $fullName = trim((string) ($profile['realName'] ?? ''));
        if ($fullName !== '') {
            $metadata['display_name'] = $fullName;
        }

        $bio = trim((string) ($profile['aboutMe'] ?? ''));
        if ($bio !== '') {
            $metadata['bio'] = $bio;
        }

        $avatar = trim((string) ($profile['userAvatar'] ?? ''));
        if ($avatar !== '') {
            $metadata['avatar_url'] = $avatar;
        }

        $country = trim((string) ($profile['countryName'] ?? ''));
        if ($country !== '') {
            $metadata['location'] = $country;
        }

        foreach ([
            'company' => 'company',
            'school' => 'school',
            'ranking' => 'ranking',
        ] as $sourceKey => $metadataKey) {
            $value = $profile[$sourceKey] ?? null;
            if (is_numeric($value)) {
                $metadata[$metadataKey] = (int) $value;
            } elseif (is_string($value) && trim($value) !== '') {
                $metadata[$metadataKey] = trim($value);
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
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['bio'] ?? null) && $metadata['bio'] !== '') {
            $summary['Bio'] = Str::limit($metadata['bio'], 160, '...');
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Country'] = $metadata['location'];
        }
        if (isset($metadata['company'])) {
            $summary['Company'] = (string) $metadata['company'];
        }
        if (isset($metadata['school'])) {
            $summary['School'] = (string) $metadata['school'];
        }
        if (isset($metadata['ranking'])) {
            $summary['Ranking'] = (string) $metadata['ranking'];
        }

        return $this->metadataSummary($summary);
    }
}
