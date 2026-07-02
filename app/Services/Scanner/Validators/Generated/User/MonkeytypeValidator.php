<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MonkeytypeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'monkeytype';
    }

    public function category(): string
    {
        return 'gaming';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Monkeytype';
    }

    public function siteUrl(): string
    {
        return 'https://monkeytype.com';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://api.monkeytype.com/users/checkName/' . rawurlencode($target);
    }

    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $data = $response->json();

        $available = $data['data']['available'] ?? null;
        if ($available === true) {
            return ['Available', ''];
        }
        if ($available === false) {
            return ['Taken', ''];
        }

        $errors = $data['validationErrors'] ?? null;
        if (is_array($errors) && $errors !== []) {
            return ['Error', implode('; ', $errors)];
        }

        return ['Error', 'Invalid status code'];
    }
}
