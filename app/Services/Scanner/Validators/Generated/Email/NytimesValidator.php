<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class NytimesValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'nytimes';
    }

    public function category(): string
    {
        return 'news';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Nytimes';
    }

    public function siteUrl(): string
    {
        return 'https://nytimes.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua-platform' => '"Android"',
                'sec-ch-ua' => '"Chromium";v="146", "Not-A.Brand";v="24", "Google Chrome";v="146"',
                'sec-ch-ua-mobile' => '?1',
            ];

            $request = Http::timeout(12)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
                'version' => 2.0,
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $loginUrl = 'https://myaccount.nytimes.com/auth/enter-email?response_type=cookie&client_id=vi&redirect_uri=https%3A%2F%2Fwww.nytimes.com';
            $init = $request->withHeaders($headers)->get($loginUrl);

            if ($init->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'NYT blocked the initial hit (403)', mode: $this->mode(), key: $this->key());
            }
            if (!preg_match('/authToken(?:&quot;|"|\\\\")\s*:\s*(?:&quot;|"|\\\\")([^&"\\\\]+)/', $init->body(), $match)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', "Couldn't find the auth_token in the page", mode: $this->mode(), key: $this->key());
            }
            $authToken = html_entity_decode($match[1], ENT_QUOTES);

            $apiHeaders = $headers + [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'req-details' => '[[it:lui]]',
                'Origin' => 'https://myaccount.nytimes.com',
                'Referer' => $loginUrl,
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
            ];

            $response = $request->withHeaders($apiHeaders)->withBody(json_encode([
                'email' => $target,
                'abraTests' => '{"AUTH_new_regilite_flow":"1_Variant","AUTH_FORGOT_PASS_LIRE":"1_Variant","AUTH_B2B_SSO":"1_Variant"}',
                'auth_token' => $authToken,
                'form_view' => 'enterEmail',
                'environment' => 'production',
            ], JSON_THROW_ON_ERROR), 'application/json')->post('https://myaccount.nytimes.com/svc/lire_ui/authorize-email/check');

            if ($response->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Bot detection triggered on the check (403)', mode: $this->mode(), key: $this->key());
            }
            if ($response->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'API acted up: ' . $response->status(), mode: $this->mode(), key: $this->key());
            }

            $furtherAction = (string) $response->json('data.further_action');
            if ($furtherAction === 'show-login') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($furtherAction === 'show-register') {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Got an weird action: ' . $furtherAction, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out') ? 'NYT took too long to answer' : $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
