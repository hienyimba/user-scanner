<?php

declare(strict_types=1);

namespace App\Services\Scanning\Connectors\Generated\Email;

use App\Services\Scanning\Connectors\BaseEmailConnector;

class ChessComEmailConnector extends BaseEmailConnector
{
    public function key(): string
    {
        return 'chess-com';
    }

    public function category(): string
    {
        return 'gaming';
    }

    protected function endpointUrl(): string
    {
        return 'https://www.chess.com/rpc/chesscom.authentication.v1.EmailValidationService/Validate';
    }

    protected function siteName(): string
    {
        return 'Chess Com';
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
        return 'https://www.chess.com/rpc/chesscom.authentication.v1.EmailValidationService/Validate/api/check-email';
    }

    /**
     * @return array<string, string>
     */
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/json;q=0.9,*/*;q=0.8',
            'Origin' => 'https://www.chess.com/rpc/chesscom.authentication.v1.EmailValidationService/Validate',
            'Referer' => 'https://www.chess.com/rpc/chesscom.authentication.v1.EmailValidationService/Validate/',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Scanner-Connector' => 'chess-com',
            'X-Scanner-Category' => 'gaming',
            'Host' => 'www.chess.com',
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
        return ['chess com account exists', 'already registered', 'already in use', 'email exists'];
    }

    /**
     * @return list<string>
     */
    protected function nonRegistrationIndicators(): array
    {
        return ['create chess com account', 'email available', 'not registered', 'sign up'];
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
