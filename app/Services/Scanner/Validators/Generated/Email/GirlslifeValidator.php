<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

// parity-class: manual-june

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class GirlslifeValidator extends BaseGeneratedValidator
{
    public function key(): string { return 'girlslife'; }
    public function category(): string { return 'entertainment'; }
    public function mode(): string { return 'email'; }
    public function siteName(): string { return 'Girlslife'; }
    public function siteUrl(): string { return 'https://girlslife.com/register/'; }

    public function check(string $target, array $options = []): ScanResult
    {
        try {
            $request = Http::timeout(10)->withOptions(['allow_redirects' => true, 'verify' => (bool) config('scanner.verify_ssl', false)]);
            $init = $request->get('https://girlslife.com/register/');
            if ($init->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to load register page: ' . $init->status(), mode: $this->mode(), key: $this->key());
            }
            if (!preg_match('/name="_wpnonce" value="([^"]+)"/', $init->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to parse response', mode: $this->mode(), key: $this->key());
            }
            $response = $request->asForm()->post('https://girlslife.com/register/', [
                'user_email-291233' => $target,
                'user_password-291233' => '',
                'confirm_user_password-291233' => '',
                'birth_date-291233' => '',
                'streetaddress-291233' => 'Miami-1',
                'zip_code-291233' => '6281',
                'form_id' => '291233',
                'um_request' => '',
                '_wpnonce' => $match[1],
                '_wp_http_referer' => '/register/',
            ]);
            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Caught by Cloudflare/WAF (403)', mode: $this->mode(), key: $this->key());
            }
            $body = $response->body();
            if (str_contains($body, 'Password is required') && !str_contains($body, 'The email you entered is incorrect')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($body, 'The email you entered is incorrect')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', mode: $this->mode(), key: $this->key());
            }
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response pattern', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }
}
