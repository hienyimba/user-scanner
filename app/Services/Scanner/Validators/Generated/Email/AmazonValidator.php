<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class AmazonValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'amazon';
    }

    public function category(): string
    {
        return 'shopping';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Amazon';
    }

    public function siteUrl(): string
    {
        return 'https://www.amazon.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $signinUrl = 'https://www.amazon.com/ap/signin?openid.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.com%2F%3F_encoding%3DUTF8%26ref_%3Dnav_ya_signin&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=usflex&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0';

        try {
            $request = Http::timeout(20)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
                'allow_redirects' => true,
                'version' => 1.1,
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'identity',
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->get($signinUrl);
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Failed to load sign-in page: HTTP ' . $response->status(), mode: $this->mode(), key: $this->key());
            }
            if ($this->isCaptcha($response->body())) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'CAPTCHA detected (IP may be flagged)', mode: $this->mode(), key: $this->key());
            }

            $fields = $this->extractFormFields($response->body());
            if ($fields === []) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Could not extract form fields', mode: $this->mode(), key: $this->key());
            }
            $fields['email'] = $target;

            $postUrl = $this->extractFormAction($response->body());
            if ($postUrl === null) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Could not find sign-in form action URL', mode: $this->mode(), key: $this->key());
            }
            if (str_starts_with($postUrl, '/')) {
                $postUrl = 'https://www.amazon.com' . $postUrl;
            }

            $response = $request->asForm()->post($postUrl, $fields);
            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'CAPTCHA triggered (IP may be flagged)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() === 429) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Rate limited', mode: $this->mode(), key: $this->key());
            }
            if (!in_array($response->status(), [200, 302], true)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected HTTP ' . $response->status(), mode: $this->mode(), key: $this->key());
            }
            if ($this->isCaptcha($response->body())) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'CAPTCHA triggered (IP may be flagged)', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($response->body(), 'id="auth-password-missing-alert"')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = match (true) {
                str_contains($message, 'timed out') => 'Connection timed out',
                str_contains($message, 'unexpected eof while reading') => 'TLS connection closed unexpectedly by Amazon; likely transport-level blocking',
                default => $e->getMessage(),
            };

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    private function extractFormFields(string $html): array
    {
        $fields = [];
        preg_match_all('/<input\\s[^>]*>/i', $html, $tags);

        foreach ($tags[0] as $tag) {
            if (preg_match('/name=["\\\']([^"\\\']*)["\\\']/', $tag, $name) && preg_match('/value=["\\\']([^"\\\']*)["\\\']/', $tag, $value)) {
                $fields[$name[1]] = html_entity_decode($value[1], ENT_QUOTES | ENT_HTML5);
            }
        }

        return $fields;
    }

    private function isCaptcha(string $html): bool
    {
        $lower = strtolower($html);
        foreach (['captcha', 'type the characters', 'robot check', 'opf-captcha'] as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function extractFormAction(string $html): ?string
    {
        preg_match_all('/<form\\s[^>]*>/i', $html, $forms);
        foreach ($forms[0] as $tag) {
            if (!preg_match('/action=["\\\']([^"\\\']*)["\\\']/', $tag, $action)) {
                continue;
            }
            $value = html_entity_decode($action[1], ENT_QUOTES | ENT_HTML5);
            $name = preg_match('/name=["\\\']([^"\\\']*)["\\\']/', $tag, $match) ? $match[1] : null;

            if ($name === 'signIn' || str_contains($value, '/ap/signin') || str_contains($value, '/ax/claim')) {
                return $value;
            }
        }

        return null;
    }
}
