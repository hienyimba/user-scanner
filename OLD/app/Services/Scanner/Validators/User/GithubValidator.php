<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\User;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Support\Facades\Http;

final class GithubValidator implements ValidatorContract
{
    public function key(): string
    {
        return 'github';
    }

    public function category(): string
    {
        return 'dev';
    }

    public function mode(): string
    {
        return 'username';
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
        $url = 'https://github.com/signup_check/username?value=' . urlencode($target);

        $request = Http::timeout(6)->withHeaders([
            'User-Agent' => config('scanner.user_agent'),
            'Accept' => 'application/json',
        ]);

        if (!empty($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        try {
            $response = $request->get($url);

            if ($response->status() === 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Available');
            }

            if ($response->status() === 422) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Taken');
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response');
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage());
        }
    }
}
