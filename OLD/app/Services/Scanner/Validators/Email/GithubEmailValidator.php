<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Email;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Support\Facades\Http;

final class GithubEmailValidator implements ValidatorContract
{
    public function key(): string
    {
        return 'github_email';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Github';
    }

    public function siteUrl(): string
    {
        return 'https://github.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $request = Http::timeout(8)->withHeaders([
            'User-Agent' => config('scanner.user_agent'),
            'Accept' => 'text/html,application/json',
        ]);

        if (!empty($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        try {
            $signup = $request->get('https://github.com/signup');
            preg_match('/data-csrf="true"\s+value="([^"]+)"/', $signup->body(), $matches);
            $token = $matches[1] ?? null;

            if ($token === null) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Token extraction failed');
            }

            $check = $request->asForm()->post('https://github.com/email_validity_checks', [
                'authenticity_token' => $token,
                'value' => $target,
            ]);

            if (str_contains($check->body(), 'already associated with an account')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered');
            }

            if ($check->status() === 200 && str_contains($check->body(), 'Email is available')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered');
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body');
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage());
        }
    }
}
