<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class FlipkartEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'flipkart';
    }

    public function category(): string
    {
        return 'shopping';
    }

    protected function endpointUrl(): string
    {
        return 'https://flipkart.com';
    }

    protected function siteName(): string
    {
        return 'Flipkart';
    }

    protected function probeMethod(): string
    {
        return 'POST';
    }

    protected function emailField(): string
    {
        return 'loginId';
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
        return '/api/5/user/check';
    }

    protected function probeUrl(): string
    {
        return 'https://flipkart.com/api/5/user/check';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://flipkart.com',
            'Referer' => 'https://flipkart.com/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'flipkart',
            'X-Scanner-Category' => 'shopping',
            'Host' => 'flipkart.com',
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
        return ['exists', 'isRegistered'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationJsonPaths(): array
    {
        return ['available'];
    }
}
