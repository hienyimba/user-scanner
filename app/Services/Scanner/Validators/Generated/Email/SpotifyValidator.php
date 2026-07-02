<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class SpotifyValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'spotify';
    }

    public function category(): string
    {
        return 'music';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Spotify';
    }

    public function siteUrl(): string
    {
        return 'https://spotify.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();

        try {
            $request = Http::timeout(10)->withOptions([
                'allow_redirects' => true,
                'verify' => (bool) config('scanner.verify_ssl', false),
                'version' => 1.1,
                'cookies' => $cookieJar,
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $request->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Linux"',
                'upgrade-insecure-requests' => '1',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-user' => '?1',
                'sec-fetch-dest' => 'document',
                'referer' => 'https://www.spotify.com/us/signup',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=0, i',
            ])->get('https://www.spotify.com/in-en/signup');

            $response = $request->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',
                'Accept-Encoding' => 'identity',
                'Content-Type' => 'application/json',
                'sec-ch-ua-platform' => '"Linux"',
                'sec-ch-ua' => '"Not(A:Brand";v="8", "Chromium";v="144", "Google Chrome";v="144"',
                'sec-ch-ua-mobile' => '?0',
                'origin' => 'https://www.spotify.com',
                'sec-fetch-site' => 'same-site',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => 'https://www.spotify.com/',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=1, i',
            ])->withBody(json_encode([
                'fields' => [
                    ['field' => 'FIELD_EMAIL', 'value' => $target],
                ],
                'client_info' => [
                    'api_key' => 'a1e486e2729f46d6bb368d6b2bcda326',
                    'app_version' => 'v2',
                    'capabilities' => [1],
                    'installation_id' => '3740cfb5-c76f-4ae9-9a94-f0989d7ae5a4',
                    'platform' => 'www',
                    'client_id' => '',
                ],
                'tracking' => [
                    'creation_flow' => '',
                    'creation_point' => 'https://www.spotify.com/us/signup',
                    'referrer' => '',
                    'origin_vertical' => '',
                    'origin_surface' => '',
                ],
            ], JSON_THROW_ON_ERROR), 'application/json')->post('https://spclient.wg.spotify.com/signup/public/v2/account/validate');

            $data = $response->json();
            $errorValue = is_array($data) && array_key_exists('error', $data) ? $data['error'] : null;
            $errorText = is_array($errorValue) ? json_encode($errorValue) : (is_scalar($errorValue) ? (string) $errorValue : '');

            if ($errorText !== '' && str_contains($errorText, 'already_exists')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (is_array($data) && array_key_exists('success', $data)) {
                return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Unexpected error [' . $response->status() . '], report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(), 'Error', 'Exception: ' . $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
