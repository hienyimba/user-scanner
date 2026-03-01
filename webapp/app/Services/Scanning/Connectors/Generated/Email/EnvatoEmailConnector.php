<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class EnvatoEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'envato';
    }

    public function category(): string
    {
        return 'dev';
    }

    protected function endpointUrl(): string
    {
        return 'https://account.envato.com/api/public/validate_email';
    }

    protected function siteName(): string
    {
        return 'Envato';
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
        return '/sign_up/check_email';
    }

    protected function probeUrl(): string
    {
        return 'https://account.envato.com/api/public/validate_email/sign_up/check_email';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://account.envato.com/api/public/validate_email',
            'Referer' => 'https://account.envato.com/api/public/validate_email/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'envato',
            'X-Scanner-Category' => 'dev',
            'Host' => 'account.envato.com',
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
        return ['envato account exists', 'already registered', 'already in use', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create envato account', 'email available', 'not registered', 'sign up'];
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
