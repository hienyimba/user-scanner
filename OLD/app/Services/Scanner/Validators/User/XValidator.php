<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\User;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Support\Facades\Http;

final class XValidator implements ValidatorContract
{
    public function key(): string
    {
        return 'x';
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
        return 'X (Twitter)';
    }

    public function siteUrl(): string
    {
        return 'https://x.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $request = Http::timeout(6)->withHeaders([
            'User-Agent' => config('scanner.user_agent'),
        ]);

        if (!empty($options['proxy'])) {
            $request = $request->withOptions(['proxy' => $options['proxy']]);
        }

        try {
            $response = $request->get('https://api.twitter.com/i/users/username_available.json', [
                'username' => $target,
                'full_name' => 'John Doe',
                'email' => 'placeholder@example.com',
            ]);

            if ($response->status() === 200 && $response->json('valid') === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Available');
            }

            if ($response->status() === 200 && $response->json('reason') === 'taken') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Taken');
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited or blocked');
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage());
        }
    }
}
