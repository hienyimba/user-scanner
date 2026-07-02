<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LinkedinValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'linkedin';
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
        return 'Linkedin';
    }

    public function siteUrl(): string
    {
        return 'https://www.linkedin.com/in';
    }

    protected function followRedirects(): bool
    {
        return false;
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.linkedin.com/in/{$target}";
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Twitterbot/1.0',
        ];
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($status === 404) {
            return ['Available', ''];
        }

        if (in_array($status, [200, 301], true)) {
            return ['Taken', ''];
        }

        return ['Error', $this->key() . ': indeterminate username response (HTTP ' . $status . ')'];
    }
}
