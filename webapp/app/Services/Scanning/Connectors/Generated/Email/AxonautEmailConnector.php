<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class AxonautEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'axonaut';
    }

    public function category(): string
    {
        return 'pipeline';
    }

    protected function endpointUrl(): string
    {
        return 'https://axonaut.com';
    }

    protected function siteName(): string
    {
        return 'Axonaut';
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
        return '/api/auth/check-email';
    }

    protected function probeUrl(): string
    {
        return 'https://axonaut.com/api/auth/check-email';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://axonaut.com',
            'Referer' => 'https://axonaut.com/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'axonaut',
            'X-Scanner-Category' => 'pipeline',
            'Host' => 'axonaut.com',
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
        return ['axonaut account exists', 'already registered', 'already in use', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create axonaut account', 'email available', 'not registered', 'sign up'];
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
