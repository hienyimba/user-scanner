<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\User;

// parity-source: C:/Users/hieny/GitHub/user-scanner/user-scanner-py-june-release/user_scanner/user_scan/other/freelancer.py
// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class FreelancerValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'freelancer';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'username';
    }

    public function siteName(): string
    {
        return 'Freelancer';
    }

    public function siteUrl(): string
    {
        return 'https://www.freelancer.com/u/{user}';
    }

    protected function requestUrl(string $target): string
    {
        return "https://www.freelancer.com/api/users/0.1/users?usernames%5B%5D={$target}&compact=true";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 10;
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();

        if ($status === 200) {
            $users = data_get($response->json(), 'result.users', []);
            if (is_array($users) && $users !== []) {
                return ['Taken', ''];
            }

            if (is_array($users)) {
                return ['Available', ''];
            }
        }

        if ($status === 404) {
            return ['Available', ''];
        }

        return ['Error', 'Unexpected response body, report it via GitHub issues.'];
    }
}
