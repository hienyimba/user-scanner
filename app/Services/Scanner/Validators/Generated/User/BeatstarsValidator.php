<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/music/beatstars.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class BeatstarsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'beatstars';
    }

    public function category(): string
    {
        return 'music';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Beatstars';
    }

    public function siteUrl(): string
    {
        return 'https://www.beatstars.com/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://core.prod.beatstars.net/auth/graphql";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function requestMethod(): string
            {
                return 'POST';
            }

            protected function requestHeaders(): array
            {
                return [
                    'Accept-Language' => 'en,en-US;q=0.9',
                ];
            }

            protected function requestBodyMode(): string
            {
                return 'json';
            }

            protected function requestBody(string $target): array
            {
                return [
                    'operationName' => 'identifierAvailable',
                    'variables' => [
                        'identifier' => $target,
                    ],
                    'query' => <<<'GRAPHQL'
query identifierAvailable($identifier: String!) {
  identifierAvailable(identifier: $identifier) {
    ...AccountBasicInfo
    __typename
  }
}

fragment AccountBasicInfo on IsIdentifierAvailableResponse {
  available
  profileDetails {
    email
    username
    artwork {
      url
      fitInUrl
      __typename
    }
    __typename
  }
  __typename
}
GRAPHQL,
                ];
            }

            protected function parseConnectorResponse(Response $response, string $target): array
            {
                $data = $response->json();
                $errors = data_get($data, 'errors', []);
                if (is_array($errors) && $errors !== []) {
                    $message = (string) data_get($errors, '0.message', '');
                    if (str_contains($message, 'ITEM_NOT_FOUND')) {
                        return ['Available', 'Username too short or invalid length'];
                    }
                    if (str_contains(strtolower($message), 'valid email or username')) {
                        return ['Available', 'Invalid username format'];
                    }
                    return ['Error', 'API Error: ' . $message];
                }

                $available = data_get($data, 'data.identifierAvailable.available');
                if ($available === true) {
                    return ['Available', ''];
                }
                if ($available === false) {
                    return ['Taken', ''];
                }

                return ['Error', 'Could not parse identifier data'];
            }
}
