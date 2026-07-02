<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class StackoverflowValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'stackoverflow';
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
        return 'Stackoverflow';
    }

    public function siteUrl(): string
    {
        return 'https://stackoverflow.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://stackoverflow.com/users/filter?search={$target}";
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();

        if ($status === 200) {
            if (str_contains($body, 'No users matched your search.')) {
                return ['Available', ''];
            }

            if (str_contains($body, '>' . $target . '<')) {
                return ['Taken', ''];
            }

            return ['Available', ''];
        }

        return ['Error', 'Unexpected status code from Stack Overflow'];
    }
}
