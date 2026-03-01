<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class BbcEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'bbc';
    }

    public function category(): string
    {
        return 'news';
    }

    protected function endpointUrl(): string
    {
        return 'https://bbc.com';
    }

    protected function siteName(): string
    {
        return 'Bbc';
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
        return 'https://bbc.com/api/check-email';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/json;q=0.9,*/*;q=0.8',
            'Origin' => 'https://bbc.com',
            'Referer' => 'https://bbc.com/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'bbc',
            'X-Scanner-Category' => 'news',
            'Host' => 'bbc.com',
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
