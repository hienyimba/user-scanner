<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class ZohoEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'zoho';
    }

    public function category(): string
    {
        return 'pipeline';
    }

    protected function endpointUrl(): string
    {
        return 'https://zoho.com';
    }

    protected function siteName(): string
    {
        return 'Zoho';
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
        return '/accounts/validate/register';
    }

    protected function probeUrl(): string
    {
        return 'https://zoho.com/accounts/validate/register';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://zoho.com',
            'Referer' => 'https://zoho.com/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'zoho',
            'X-Scanner-Category' => 'pipeline',
            'Host' => 'zoho.com',
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
        return ['zoho account exists', 'already registered', 'already in use', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create zoho account', 'email available', 'not registered', 'sign up'];
    }

    /**
     * @return list<string>
     */
    protected function registrationJsonPaths(): array
    {
        return ['isUserExists', 'exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationJsonPaths(): array
    {
        return ['isAvailable', 'available'];
    }
}
