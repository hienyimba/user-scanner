<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class MixValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'mix';
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
        return 'Mix';
    }

    public function siteUrl(): string
    {
        return 'https://mix.com';
    }

    protected function requestUrl(string $target): string
    {
        return "https://mix.com/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => config('scanner.user_agent'),
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

        return ['Error', 'HTTP ' . $status];
    }
}
