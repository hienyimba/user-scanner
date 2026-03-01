<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class JustwatchEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'justwatch';
    }

    public function category(): string
    {
        return 'entertainment';
    }

    protected function endpointUrl(): string
    {
        return 'https://identitytoolkit.googleapis.com/v1/accounts:createAuthUri';
    }

    protected function siteName(): string
    {
        return 'Justwatch';
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
        return 'https://identitytoolkit.googleapis.com/v1/accounts:createAuthUri/api/check-email';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/json;q=0.9,*/*;q=0.8',
            'Origin' => 'https://identitytoolkit.googleapis.com/v1/accounts:createAuthUri',
            'Referer' => 'https://identitytoolkit.googleapis.com/v1/accounts:createAuthUri/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'justwatch',
            'X-Scanner-Category' => 'entertainment',
            'Host' => 'identitytoolkit.googleapis.com',
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
        return ['justwatch account exists', 'already registered', 'already in use', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create justwatch account', 'email available', 'not registered', 'sign up'];
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
