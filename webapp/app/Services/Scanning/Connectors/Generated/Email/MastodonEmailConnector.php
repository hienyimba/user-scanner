<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class MastodonEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'mastodon';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function endpointUrl(): string
    {
        return 'https://mastodon.social';
    }

    protected function siteName(): string
    {
        return 'Mastodon';
    }

    protected function probeMethod(): string
    {
        return 'POST';
    }

    protected function emailField(): string
    {
        return 'email';
    }

    /**
     * @return array<string, string>
     */
    protected function probeQuery(string $email): array
    {
        return [
            $this->emailField() => $email,
            'source' => 'webapp-scanner',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function probeBody(string $email): array
    {
        return [
            $this->emailField() => $email,
            'source' => 'webapp-scanner',
        ];
    }

    protected function probeEndpointPath(): string
    {
        return '/auth/sign_up';
    }

    protected function probeUrl(): string
    {
        return 'https://mastodon.social/auth/sign_up';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://mastodon.social',
            'Referer' => 'https://mastodon.social/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'mastodon',
            'X-Scanner-Category' => 'social',
            'Host' => 'mastodon.social',
        ];
    }

    /**
     * @return list<int>
     */
    protected function registeredStatusCodes(): array
    {
        return [200, 201, 409, 422];
    }

    /**
     * @return list<int>
     */
    protected function nonRegisteredStatusCodes(): array
    {
        return [404, 204];
    }

    /**
     * @return list<string>
     */
    protected function registrationIndicators(): array
    {
        return ['mastodon account exists', 'already registered', 'already in use', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create mastodon account', 'email available', 'not registered', 'sign up'];
    }

    /**
     * @return list<string>
     */
    protected function registrationJsonPaths(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationJsonPaths(): array
    {
        return [];
    }
}
