<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class WalmartEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'walmart';
    }

    public function category(): string
    {
        return 'shopping';
    }

    protected function endpointUrl(): string
    {
        return 'https://identity.walmart.com/orchestra/idp/graphql';
    }

    protected function siteName(): string
    {
        return 'Walmart';
    }

    protected function probeMethod(): string
    {
        return 'POST';
    }

    protected function emailField(): string
    {
        return 'emailAddress';
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
        return '/account/api/check-email';
    }

    protected function probeUrl(): string
    {
        return 'https://identity.walmart.com/orchestra/idp/graphql/account/api/check-email';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://identity.walmart.com/orchestra/idp/graphql',
            'Referer' => 'https://identity.walmart.com/orchestra/idp/graphql/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'walmart',
            'X-Scanner-Category' => 'shopping',
            'Host' => 'identity.walmart.com',
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
        return ['already in use', 'account exists', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create account', 'join now', 'available'];
    }

    /**
     * @return list<string>
     */
    protected function registrationJsonPaths(): array
    {
        return ['exists', 'accountExists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationJsonPaths(): array
    {
        return ['available'];
    }
}
