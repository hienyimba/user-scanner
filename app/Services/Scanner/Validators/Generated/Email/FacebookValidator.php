<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class FacebookValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'facebook';
    }

    public function category(): string
    {
        return 'social';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Facebook';
    }

    public function siteUrl(): string
    {
        return 'https://facebook.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $showUrl = $this->siteUrl();

        try {
            $client = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                    'version' => 2.0,
                ]);

            if (!empty($options['proxy'])) {
                $client = $client->withOptions(['proxy' => $options['proxy']]);
            }

            $headers1 = [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            ];
            $client->withHeaders($headers1)->get('https://m.facebook.com/login/');

            $headers2 = [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Encoding' => 'identity',
                'upgrade-insecure-requests' => '1',
                'sec-fetch-site' => 'cross-site',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-user' => '?1',
                'sec-fetch-dest' => 'document',
                'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Linux"',
                'referer' => 'https://www.google.com/',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=0, i',
            ];
            $res2 = $client->withHeaders($headers2)->get('https://www.facebook.com', ['_rdr' => '']);
            $html = $res2->body();

            $lsd = null;
            foreach ([
                '/\["LSD",\[\],\{"token":"([^"]+)"\}/',
                '/name="lsd"\s+value="([^"]+)"/',
                '/"lsd":"([^"]+)"/',
            ] as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $lsd = $matches[1];
                    break;
                }
            }

            $jazoest = null;
            foreach ([
                '/jazoest=(\d+)/',
                '/name="jazoest"\s+value="(\d+)"/',
            ] as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $jazoest = $matches[1];
                    break;
                }
            }

            if (!$lsd || !$jazoest) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Token extraction failed (LSD: ' . ($lsd ? 'True' : 'False') . ', Jazoest: ' . ($jazoest ? 'True' : 'False') . ')', mode: $this->mode(), key: $this->key());
            }

            $payload = [
                'jazoest' => $jazoest,
                'lsd' => $lsd,
                'email' => $target,
                'did_submit' => '1',
                '__user' => '0',
                '__a' => '1',
                '__req' => '7',
            ];

            $headers3 = [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua-full-version-list' => '"Google Chrome";v="143.0.7499.192", "Chromium";v="143.0.7499.192", "Not A(Brand";v="24.0.0.0"',
                'sec-ch-ua-platform' => '"Linux"',
                'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
                'sec-ch-ua-model' => '""',
                'sec-ch-ua-mobile' => '?0',
                'x-asbd-id' => '359341',
                'x-fb-lsd' => $lsd,
                'sec-ch-prefers-color-scheme' => 'dark',
                'sec-ch-ua-platform-version' => '""',
                'origin' => 'https://www.facebook.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => 'https://www.facebook.com/login/identify/?ctx=recover&ars=facebook_login&from_login_screen=0',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=1, i',
            ];
            $response = $client->withHeaders($headers3)
                ->asForm()
                ->post('https://www.facebook.com/ajax/login/help/identify.php?ctx=recover', $payload);

            $body = $response->body();
            if (str_contains($body, 'These accounts matched your search') || str_contains($body, 'redirectPageTo')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Registered', '', mode: $this->mode(), key: $this->key());
            }
            if (str_contains($body, 'No search results') || str_contains($body, 'Your search did not return any results.')) {
                return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Not Registered', '', mode: $this->mode(), key: $this->key());
            }

            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected error, report it via GitHub issues', mode: $this->mode(), key: $this->key());
        } catch (\Throwable $e) {
            return new ScanResult($target, $this->category(), $this->siteName(), $showUrl, 'Error', 'Unexpected exception: ' . $e->getMessage(), mode: $this->mode(), key: $this->key());
        }
    }

    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Facebook uses a custom recovery flow'];
    }
}
