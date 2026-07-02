<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class WondershareValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'wondershare';
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
        return 'Wondershare';
    }

    public function siteUrl(): string
    {
        return 'https://wondershare.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = $this->siteUrl();
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'identity',
            'sec-ch-ua-platform' => '"Android"',
            'x-lang' => 'en-us',
            'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
            'sec-ch-ua-mobile' => '?1',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-dest' => 'empty',
            'referer' => 'https://accounts.wondershare.com/m/login?source=&redirect_uri=https://www.wondershare.com/?source=&site=www.wondershare.com&verify=no',
            'accept-language' => 'en-US,en;q=0.9',
            'priority' => 'u=1, i',
        ];
        try {
            $jar = new CookieJar();
            $client = Http::timeout(15)->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
                'cookies' => $jar,
            ]);

            if (!empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $csrf = $client->withHeaders($headers)->get('https://accounts.wondershare.com/api/v3/csrf-token');
            if ($csrf->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Handshake failed: ' . $csrf->status(), mode: $this->mode(), key: $this->key());
            }

            $tokenCookie = $jar->getCookieByName('req_identity');
            if ($tokenCookie === null) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'req_identity cookie missing from handshake', mode: $this->mode(), key: $this->key());
            }

            $inspect = $client
                ->withHeaders(array_merge($headers, [
                    'x-csrf-token' => $tokenCookie->getValue(),
                    'Content-Type' => 'application/json',
                    'origin' => 'https://accounts.wondershare.com',
                    'referer' => 'https://accounts.wondershare.com/m/register',
                ]))
                ->withBody(json_encode(['email' => $target], JSON_UNESCAPED_SLASHES), 'application/json')
                ->send('PUT', 'https://accounts.wondershare.com/api/v3/account/inspect');

            if ($inspect->status() === 403) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Caught by WAF (403)', mode: $this->mode(), key: $this->key());
            }
            if ($inspect->status() !== 200) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'HTTP Error: ' . $inspect->status(), mode: $this->mode(), key: $this->key());
            }

            $data = $inspect->json();
            $exist = data_get($data, 'data.exist');
            if ($exist === 1) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if ($exist === 2) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected exist value: ' . $exist, mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Wondershare uses a custom CSRF flow'];
    }
}
