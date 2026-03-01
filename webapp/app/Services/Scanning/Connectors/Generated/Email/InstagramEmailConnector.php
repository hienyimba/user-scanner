<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class InstagramEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'instagram';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function endpointUrl(): string
    {
        return 'https://instagram.com';
    }

    protected function siteName(): string
    {
        return 'Instagram';
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
        return '/accounts/web_create_ajax/attempt/';
    }

    protected function probeUrl(): string
    {
        return 'https://instagram.com/accounts/web_create_ajax/attempt/';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://instagram.com',
            'Referer' => 'https://instagram.com/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'instagram',
            'X-Scanner-Category' => 'social',
            'Host' => 'instagram.com',
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
        return ['instagram account exists', 'already registered', 'already in use', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create instagram account', 'email available', 'not registered', 'sign up'];
    }

    /**
     * @return list<string>
     */
    protected function registrationJsonPaths(): array
    {
        return ['email_is_taken', 'errors.email'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationJsonPaths(): array
    {
        return ['email_is_available'];
    }
}
