<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AirlinersValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'airliners';
    }

    public function category(): string
    {
        return 'community';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Airliners';
    }

    public function siteUrl(): string
    {
        return 'https://www.airliners.net/user/profile';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.airliners.net/user/{$target}/profile";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 20;
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 404) {
            return ['Available', ''];
        }

        if ($status === 200) {
            return ['Taken', ''];
        }

        return ['Error', 'HTTP ' . $status];
    }
}
