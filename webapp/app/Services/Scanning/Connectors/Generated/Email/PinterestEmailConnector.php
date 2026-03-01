<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class PinterestEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'pinterest';
    }

    public function category(): string
    {
        return 'social';
    }

    protected function endpointUrl(): string
    {
        return 'https://www.pinterest.com/resource/ApiResource/get/';
    }

    protected function siteName(): string
    {
        return 'Pinterest';
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
        return '/resource/EmailExistsResource/get/';
    }

    protected function probeUrl(): string
    {
        return 'https://www.pinterest.com/resource/ApiResource/get/resource/EmailExistsResource/get/';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://www.pinterest.com/resource/ApiResource/get',
            'Referer' => 'https://www.pinterest.com/resource/ApiResource/get/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'pinterest',
            'X-Scanner-Category' => 'social',
            'Host' => 'www.pinterest.com',
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
        return ['pinterest account exists', 'already registered', 'already in use', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create pinterest account', 'email available', 'not registered', 'sign up'];
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
