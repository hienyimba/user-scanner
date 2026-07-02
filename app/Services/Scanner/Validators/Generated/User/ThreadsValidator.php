<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class ThreadsValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'threads';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Threads';
    }

    public function siteUrl(): string
    {
        return 'https://www.threads.net/@';
    }

    protected function requestUrl(string $target): string
    {
        return 'https://www.threads.net/api/v1/users/web_profile_info/';
    }

    protected function requestHeadersForTarget(string $target): array
    {
        return [
            'X-IG-App-ID' => '936619743392459',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.9',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => "https://www.threads.net/@{$target}",
        ];
    }

    protected function requestQuery(string $target): array
    {
        return [
            'username' => $target,
        ];
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

        return ['Error', $this->key() . ': blocked/rate-limited (HTTP ' . $status . ')'];
    }
}
