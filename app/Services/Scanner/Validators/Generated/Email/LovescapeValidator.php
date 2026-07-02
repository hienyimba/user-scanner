<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;

final class LovescapeValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'lovescape';
    }

    public function category(): string
    {
        return 'adult';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Lovescape';
    }

    public function siteUrl(): string
    {
        return 'https://lovescape.com';
    }

    protected function requestMethod(): string
    {
        return 'POST';
    }

    protected function requestUrl(string $target): string
    {
        return "https://lovescape.com/api/front/auth/signup";
    }

    protected function followRedirects(): bool
    {
        return true;
    }

    protected function timeoutSeconds(): int
    {
        return 7;
    }

    protected function requestHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Encoding' => 'identity',
            'Content-Type' => 'text/plain;charset=UTF-8',
            'sec-ch-ua-platform' => '"Android"',
            'sec-ch-ua' => '"Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
            'sec-ch-ua-mobile' => '?1',
            'Origin' => 'https://lovescape.com',
            'Referer' => 'https://lovescape.com/signup',
            'Priority' => 'u=1, i',
        ];
    }

    protected function requestRawBody(string $target): ?string
    {
        return json_encode([
            'username' => '_W3ak3n3d_Cut3n3ss86541',
            'email' => $target,
            'password' => 'igy8868yiyy',
            'recaptcha' => '',
            'fingerprint' => '',
            'modelName' => '',
            'isPwa' => false,
            'affiliateId' => '',
            'trafficSource' => '',
            'isUnThrottled' => false,
            'hasActionParam' => false,
            'source' => 'page_signup',
            'device' => 'mobile',
            'deviceName' => 'Android Mobile',
            'browser' => 'Chrome',
            'os' => 'Android',
            'locale' => 'en',
            'authType' => 'native',
            'ampl' => [
                'ep' => [
                    'source' => 'page_signup',
                    'startSessionUrl' => '/create-ai-sex-girlfriend/style',
                    'firstVisitedUrl' => '/create-ai-sex-girlfriend/style',
                    'referrerHost' => 'hakurei.us-cdnbo.org',
                    'referrerId' => 'us-cdnbo',
                    'signupUrl' => '/signup',
                    'page' => 'signup',
                    'project' => 'Lovescape',
                    'isCookieAccepted' => true,
                    'displayMode' => 'browser',
                ],
                'up' => [
                    'source' => 'page_signup',
                    'startSessionUrl' => '/create-ai-sex-girlfriend/style',
                    'firstVisitedUrl' => '/create-ai-sex-girlfriend/style',
                    'referrerHost' => 'hakurei.us-cdnbo.org',
                    'referrerId' => 'us-cdnbo',
                    'signupUrl' => '/signup',
                ],
                'device_id' => '',
                'session_id' => 1774884558258,
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        $status = $response->status();
        if ($status === 403) {
            return ['Error', '403'];
        }

        $data = $response->json();
        $error = (string) ($data['error'] ?? '');
        if (str_contains($error, 'Email is already used')) {
            return ['Registered', ''];
        }
        if (str_contains($error, 'recaptcha is required')) {
            return ['Not Registered', ''];
        }

        return ['Error', 'Unexpected: ' . $error];
    }
}
