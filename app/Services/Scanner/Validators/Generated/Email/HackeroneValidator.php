<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\ScannerDiagnostics;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

final class HackeroneValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'hackerone';
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
        return 'Hackerone';
    }

    public function siteUrl(): string
    {
        return 'https://hackerone.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $cookieJar = new CookieJar();
        $startedAt = microtime(true);
        $response = null;
        $requestStage = 'handshake';
        $finish = function (string $status, string $reason = '') use ($target, $options, $startedAt, &$response, &$requestStage): ScanResult {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(),
                $status, ScannerDiagnostics::redact($reason, [$target]), mode: $this->mode(), key: $this->key(),
                metadata: $this->mergeRequestDiagnostics(['request_stage' => $requestStage], $options, $response, $startedAt));
        };

        try {
            $request = Http::timeout(15)
                ->withOptions([
                    'allow_redirects' => true,
                    'cookies' => $cookieJar,
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Encoding' => 'identity',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-full-version' => '"145.0.7632.109"',
                'sec-ch-ua-arch' => '"x86"',
                'sec-ch-ua-platform' => '"Linux"',
                'sec-ch-ua-platform-version' => '""',
                'sec-ch-ua-model' => '""',
                'sec-ch-ua-bitness' => '"64"',
                'sec-ch-ua-full-version-list' => '"Not:A-Brand";v="99.0.0.0", "Google Chrome";v="145.0.7632.109", "Chromium";v="145.0.7632.109"',
                'upgrade-insecure-requests' => '1',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-user' => '?1',
                'sec-fetch-dest' => 'document',
                'referer' => 'https://hackerone.com/users/sign_in',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=0, i',
            ])->get('https://hackerone.com/sign_up');

            if ($response->status() === 403) {
                return $finish('Error', 'Caught by WAF (403) during Handshake');
            }
            if ($response->status() !== 200) {
                return $finish('Error', 'Unexpected HTTP '.$response->status().' during HackerOne Handshake');
            }
            if (!preg_match('/name="csrf-token" content="([^"]+)"/', $response->body(), $match)) {
                return $finish('Error', 'Failed to extract CSRF token');
            }

            $requestStage = 'validation';
            $response = null;
            $response = $request->withHeaders([
                'sec-ch-ua-full-version-list' => '"Not:A-Brand";v="99.0.0.0", "Google Chrome";v="145.0.7632.109", "Chromium";v="145.0.7632.109"',
                'sec-ch-ua-platform' => '"Linux"',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Google Chrome";v="145", "Chromium";v="145"',
                'sec-ch-ua-bitness' => '"64"',
                'sec-ch-ua-model' => '""',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-arch' => '"x86"',
                'x-requested-with' => 'XMLHttpRequest',
                'sec-ch-ua-full-version' => '"145.0.7632.109"',
                'accept' => 'application/json, text/javascript, */*; q=0.01',
                'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'x-csrf-token' => $match[1],
                'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',
                'sec-ch-ua-platform-version' => '""',
                'origin' => 'https://hackerone.com',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-dest' => 'empty',
                'referer' => 'https://hackerone.com/users/sign_up',
                'accept-encoding' => 'identity',
                'accept-language' => 'en-US,en;q=0.9',
                'priority' => 'u=1, i',
            ])->asForm()->post('https://hackerone.com/users', [
                'user[name]' => 'St33l_h3art_g3t_n0_l0v3',
                'user[username]' => 'kn0wl3dg3_is_curs3',
                'user[email]' => $target,
                'user[password]' => 'thisw0rldwasn3v3rg00d',
                'user[password_confirmation]' => 'mismatch_on_purpose',
            ]);

            if ($response->status() === 403) {
                return $finish('Error', 'Caught by WAF (403) during Validation');
            }
            if ($response->status() === 429) {
                return $finish('Error', 'Rate limited by HackerOne (429)');
            }

            // A known-looking error string in a gateway/error response is not
            // account evidence. Validate transport status before either verdict.
            if ($response->status() !== 200) {
                return $finish('Error', 'Unexpected response: ' . $response->status());
            }

            // Verdict guards: only errors.email carries account evidence. A taken
            // username, missing required fields we never submitted, or any
            // non-200 payload is not email evidence and must stay an Error.
            $errors = $response->json('errors');

            $emailErrors = is_array($errors) ? ($errors['email'] ?? null) : null;
            if ($emailErrors === ['has already been taken']) {
                return $finish('Registered');
            }

            if (!is_array($errors)) {
                return $finish('Error', 'Unexpected response: ' . $response->status());
            }

            if (array_key_exists('email', $errors)) {
                return $finish('Error', 'HackerOne returned an unrecognized email validation error; account status unknown');
            }

            // A negative verdict is only safe when the validation contract ran to
            // completion on inputs this probe controls: the deliberate password
            // confirmation mismatch must be the only complaint. Any other error
            // field (username, first_name, last_name, ...) means the request was
            // rejected before email evidence could be established.
            if (array_keys($errors) === ['password_confirmation']
                && $errors['password_confirmation'] === ["doesn't match Password"]) {
                return $finish('Not Registered');
            }

            $unexpectedFields = implode(', ', array_map('strval', array_keys($errors)));
            $reason = $unexpectedFields === ''
                ? 'HackerOne returned an empty validation payload; account status unknown'
                : 'HackerOne validation contract changed (unexpected error fields: ' . $unexpectedFields . '); account status unknown';

            return $finish('Error', $reason);
        } catch (\Throwable $e) {
            return $finish('Error', ScannerDiagnostics::exception($e, 'validator.'.$requestStage, [
                'mode' => $this->mode(), 'module' => $this->key(), 'redact_values' => [$target],
            ]));
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
