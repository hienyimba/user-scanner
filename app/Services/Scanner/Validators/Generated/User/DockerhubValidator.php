<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class DockerhubValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'dockerhub';
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
        return 'Dockerhub';
    }

    public function siteUrl(): string
    {
        return 'https://hub.docker.com/u/{user}';
    }

    protected function requestMethod(): string
    {
        return 'GET';
    }

    protected function requestUrl(string $target): string
    {
        return "https://hub.docker.com/v2/users/{$target}/";
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
        $status = $response->status();
        $body = strtolower($response->body());

        if ($blocked = $this->detectBlockedOrChallenged($response)) {
            return $blocked;
        }

        $availableStatuses = [404];
        $takenStatuses = [200];
        $availableIndicators = [];
        $takenIndicators = [];

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

        if (isset($data['id']) && is_numeric($data['id'])) {
            $metadata['dockerhub_id'] = (int) $data['id'];
        } elseif (isset($data['id'])) {
            $metadata['dockerhub_id'] = trim((string) $data['id']);
        }

        $fullName = trim((string) ($data['full_name'] ?? ''));
        if ($fullName !== '') {
            $metadata['display_name'] = $fullName;
        }

        $company = trim((string) ($data['company'] ?? ''));
        if ($company !== '') {
            $metadata['company'] = $company;
        }

        $location = trim((string) ($data['location'] ?? ''));
        if ($location !== '') {
            $metadata['location'] = $location;
        }

        $joined = trim((string) ($data['date_joined'] ?? ''));
        if ($joined !== '') {
            try {
                $metadata['created_at'] = (new \DateTimeImmutable($joined))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
            } catch (\Throwable) {
                $metadata['created_at'] = $joined;
            }
        }

        $type = trim((string) ($data['type'] ?? ''));
        if ($type !== '') {
            $metadata['account_type'] = $type;
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

        if (isset($metadata['dockerhub_id'])) {
            $summary['ID'] = (string) $metadata['dockerhub_id'];
        }
        if (is_string($metadata['display_name'] ?? null) && $metadata['display_name'] !== '') {
            $summary['Name'] = $metadata['display_name'];
        }
        if (is_string($metadata['company'] ?? null) && $metadata['company'] !== '') {
            $summary['Company'] = $metadata['company'];
        }
        if (is_string($metadata['location'] ?? null) && $metadata['location'] !== '') {
            $summary['Location'] = $metadata['location'];
        }
        if (is_string($metadata['account_type'] ?? null) && $metadata['account_type'] !== '') {
            $summary['Type'] = $metadata['account_type'];
        }

        return $this->metadataSummary($summary);
    }
}
