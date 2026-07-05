<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/freelancer.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FreelancerValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'freelancer';
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
        return 'Freelancer';
    }

    public function siteUrl(): string
    {
        return 'https://www.freelancer.com/u/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.freelancer.com/api/users/0.1/users?usernames%5B%5D={$target}&compact=true";
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

        if ($status === 200) {
            $users = data_get($response->json(), 'result.users', []);
            if (is_array($users) && $users !== []) {
                return ['Taken', ''];
            }

            if (is_array($users)) {
                return ['Available', ''];
            }
        }

        if ($status === 404) {
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

        $users = data_get($response->json(), 'result.users');
        if (!is_array($users) || $users === []) {
            return [];
        }

        $user = array_values($users)[0] ?? null;
        if (!is_array($user)) {
            return [];
        }

        $metadata = [
            'username' => trim((string) ($user['username'] ?? '')) ?: $target,
            'sources' => ['api_json'],
        ];

        if (isset($user['id']) && is_numeric($user['id'])) {
            $metadata['freelancer_id'] = (int) $user['id'];
        }

        $displayName = trim((string) ($user['display_name'] ?? ''));
        if ($displayName !== '') {
            $metadata['display_name'] = $displayName;
        }

        $role = trim((string) ($user['role'] ?? ''));
        if ($role !== '') {
            $metadata['account_type'] = $role;
        }

        $registrationDate = trim((string) ($user['registration_date'] ?? ''));
        if ($registrationDate !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($registrationDate))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $registrationDate;
            }
        }

        $location = [];
        $city = trim((string) data_get($user, 'location.city', ''));
        $country = trim((string) data_get($user, 'location.country.name', ''));
        if ($city !== '') {
            $location[] = $city;
        }
        if ($country !== '') {
            $location[] = $country;
        }
        if ($location !== []) {
            $metadata['location'] = implode(', ', $location);
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

        if (isset($metadata['freelancer_id'])) {
            $summary['ID'] = (string) $metadata['freelancer_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['account_type'] ?? null) && $metadata['account_type'] !== '') {
            $summary['Role'] = $metadata['account_type'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['created_at'] ?? null) && $metadata['created_at'] !== '') {
            $summary['Registered'] = $metadata['created_at'];
        }

        return $this->metadataSummary($summary);
    }
}
