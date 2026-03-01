<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class BabestationEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'babestation';
    }

    public function category(): string
    {
        return 'adult';
    }

    protected function endpointUrl(): string
    {
        return 'https://www.babestation.tv/user/send/username-reminder';
    }

    protected function siteName(): string
    {
        return 'Babestation';
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
        return '/api/check-email';
    }

    protected function probeUrl(): string
    {
        return 'https://www.babestation.tv/user/send/username-reminder/api/check-email';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json,text/plain,*/*',
            'Origin' => 'https://www.babestation.tv/user/send/username-reminder',
            'Referer' => 'https://www.babestation.tv/user/send/username-reminder/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'babestation',
            'X-Scanner-Category' => 'adult',
            'Host' => 'www.babestation.tv',
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
        return ['email already used', 'already exists', 'account exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['new account', 'email available', 'join now'];
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
