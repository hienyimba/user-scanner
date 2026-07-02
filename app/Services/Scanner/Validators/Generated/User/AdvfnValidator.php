<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class AdvfnValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'advfn'; }
    public function category(): string { return 'finance'; }
    public function mode(): string { return 'username'; }
    public function siteName(): string { return 'Advfn'; }
    public function siteUrl(): string { return 'https://uk.advfn.com/forum/profile'; }
    protected function requestUrl(string $target): string { return "https://uk.advfn.com/forum/profile/{$target}"; }
    protected function requestHeaders(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer' => 'https://uk.advfn.com/',
        ];
    }
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        $body = $response->body();
        if (
            str_contains($body, 'Profile | ADVFN')
            || str_contains($body, 'Member since:')
            || (
                str_contains($body, 'Followers')
                && str_contains($body, 'Following')
                && str_contains($body, 'Public Portfolios')
            )
        ) {
            return ['Taken', ''];
        }

        if ($status === 404 || str_contains($body, 'ADVFN ERROR - Page Not Found')) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
