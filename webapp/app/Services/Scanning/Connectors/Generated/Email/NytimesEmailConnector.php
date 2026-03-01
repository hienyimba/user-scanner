<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class NytimesEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'nytimes';
    }

    public function category(): string
    {
        return 'news';
    }

    protected function endpointUrl(): string
    {
        return 'https://nytimes.com';
    }

    protected function siteName(): string
    {
        return 'Nytimes';
    }

    protected function probeMethod(): string
    {
        return 'GET';
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
        return '/api/check-email';
    }

    protected function probeUrl(): string
    {
        return 'https://nytimes.com/api/check-email';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/json;q=0.9,*/*;q=0.8',
            'Origin' => 'https://nytimes.com',
            'Referer' => 'https://nytimes.com/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'nytimes',
            'X-Scanner-Category' => 'news',
            'Host' => 'nytimes.com',
        ];
    }

    /**
     * @return list<int>
     */
    protected function registeredStatusCodes(): array
    {
        return [200, 302, 409];
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
        return ['already subscribed', 'already registered', 'subscriber exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['subscribe', 'newsletter signup', 'create account'];
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
