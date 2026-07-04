<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class LuarocksValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'luarocks'; }
    public function category(): string { return 'dev'; }
    public function mode(): string { return 'email'; }
    public function siteName(): string { return 'Luarocks'; }
    public function siteUrl(): string { return 'https://luarocks.org'; }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(15)->withOptions(['allow_redirects' => true, 'verify' => (bool) config('scanner.verify_ssl', false)]);
            $init = $request->get('https://luarocks.org/login');
            if ($init->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to load validation frame: ' . $init->status(), mode: $this->mode(), key: $this->key());
            }
            if (!preg_match('/name="csrf_token" value="([^"]+)"/', $init->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Could not parse LuaRocks state CSRF token', mode: $this->mode(), key: $this->key());
            }
            $response = $request->asForm()->post('https://luarocks.org/user/forgot_password', [
                'csrf_token' => $match[1],
                'email' => $target,
            ]);
            $body = strtolower($response->body());
            if (str_contains($body, 'password reset link has been sent')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($body, "don't know anyone") || str_contains($body, 'don&#39;t know anyone') || str_contains($body, 'know anyone with that email')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', mode: $this->mode(), key: $this->key());
            }
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected target response markup signature', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }
}
