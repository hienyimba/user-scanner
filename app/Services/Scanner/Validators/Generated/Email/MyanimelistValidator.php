<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\ScannerDiagnostics;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class MyanimelistValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'myanimelist';
    }

    public function category(): string
    {
        return 'entertainment';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Myanimelist';
    }

    public function siteUrl(): string
    {
        return 'https://myanimelist.net';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $response = null;
        $finish = function (string $status, string $reason = '') use ($target, $options, $startedAt, &$response): ScanResult {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(),
                $status, ScannerDiagnostics::redact($reason, [$target]), mode: $this->mode(), key: $this->key(),
                metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt));
        };
        try {
            $request = Http::timeout(7)
                ->withOptions([
                    'verify' => (bool) config('scanner.verify_ssl', false),
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36',
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Encoding' => 'identity',
                    'sec-ch-ua-platform' => '"Android"',
                    'x-requested-with' => 'XMLHttpRequest',
                    'origin' => 'https://myanimelist.net',
                    'sec-fetch-site' => 'same-origin',
                    'sec-fetch-mode' => 'cors',
                    'sec-fetch-dest' => 'empty',
                    'referer' => 'https://myanimelist.net/register.php?',
                    'accept-language' => 'en-US,en;q=0.9',
                    'priority' => 'u=1, i',
                ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->asForm()->post('https://myanimelist.net/signup/email/validate', [
                'email' => $target,
            ]);

            if ($response->status() === 403) {
                return $finish('Error', 'Caught by WAF or IP Block (403)');
            }
            if ($response->status() === 429) {
                return $finish('Error', 'Rate limited by MyAnimeList (429)');
            }
            if ($response->status() !== 200) {
                return $finish('Error', 'HTTP Error: ' . $response->status());
            }

            $data = $response->json();
            $errors = is_array($data) ? ($data['errors'] ?? []) : null;
            if (!is_array($data) || !is_array($errors)) {
                return $finish('Error', 'Unexpected MyAnimeList response structure');
            }
            foreach ($errors as $error) {
                $message = is_array($error) && is_string($error['message'] ?? null) ? strtolower($error['message']) : '';
                if (str_contains($message, 'already have an account')) {
                    return $finish('Registered');
                }
            }

            if ($errors !== []) {
                return $finish('Error', 'MyAnimeList rejected validation without account evidence');
            }

            // Preserve [] versus {}: assoc JSON decoding collapses both into
            // an empty PHP array, but only an explicit empty list is the API's
            // known negative contract.
            $payload = json_decode($response->body());
            $respData = is_object($payload) ? ($payload->data ?? null) : null;
            if (is_array($respData) && $respData === []) {
                return $finish('Not Registered');
            }

            return $finish('Error', 'Unexpected MyAnimeList response structure; account status unknown');
        } catch (\Throwable $e) {
            $safeMessage = ScannerDiagnostics::exception($e, 'validator.request', [
                'mode' => $this->mode(), 'module' => $this->key(), 'redact_values' => [$target],
            ]);
            $message = strtolower($e->getMessage());
            $reason = str_contains($message, 'timed out')
                ? (str_contains($message, 'read') ? 'Server took too long to respond (Read Timeout)' : 'Connection timed out! maybe region blocks')
                : $safeMessage;

            return $finish('Error', $reason);
        }
    }

    /** @return array{0:string,1:string} */
    protected function parseConnectorResponse(Response $response, string $target): array
    {
        return ['Error', 'Unexpected response'];
    }
}
