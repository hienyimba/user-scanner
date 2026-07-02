<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class BbcValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'bbc';
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
        return 'Bbc';
    }

    public function siteUrl(): string
    {
        return 'https://bbc.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36',
                'Accept' => 'application/json',
                'Origin' => 'https://account.bbc.com',
                'Accept-Encoding' => 'identity',
            ];

            $request = Http::timeout(7)->withOptions([
                'allow_redirects' => true,
                'cookies' => $cookieJar,
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $loginUrl = 'https://account.bbc.com/auth/identifier/signin?realm=%2F&clientId=Account&action=register&ptrt=https%3A%2F%2Fwww.bbc.com%2F&userOrigin=BBCS_BBC&purpose=free';
            $response = $request->withHeaders($headers)->get($loginUrl);

            if (!preg_match('/nonce=([a-zA-Z0-9\-_]+)/', (string) $response->effectiveUri(), $nonceMatch)
                && !preg_match('/"nonce":"([a-zA-Z0-9\-_]+)"/', $response->body(), $nonceMatch)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unable to extract nonce, report it via GitHub issues', mode: $this->mode(), key: $this->key());
            }

            $nonce = $nonceMatch[1];
            $check = $request->withHeaders($headers)->post(
                'https://account.bbc.com/auth/identifier/check?action=sign-in&clientId=Account&context=international&isCasso=false&journeyGroupType=sign-in&nonce=' . urlencode($nonce) . '&ptrt=https%3A%2F%2Fwww.bbc.com%2F&purpose=free&realm=%2F&redirectUri=https%3A%2F%2Fsession.bbc.com%2Fsession%2Fcallback%3Frealm%3D%2F&service=IdRegisterService&userOrigin=BBCS_BBC',
                ['userIdentifier' => $target]
            );

            $exists = $check->json('exists');
            if ($exists === true) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($exists === false) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected response body, report it on github', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Read Timeout)' : 'Connection timed out!')
                : $e->getMessage();
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', $reason, mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
