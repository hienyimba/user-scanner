<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\Email;

use App\DTO\ScanResult;
use App\Services\Scanner\ScannerDiagnostics;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use Illuminate\Support\Facades\Http;

final class FirefoxValidator extends BaseGeneratedValidator
{
    public function key(): string
    {
        return 'firefox';
    }

    public function category(): string
    {
        return 'other';
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return 'Firefox';
    }

    public function siteUrl(): string
    {
        return 'https://firefox.com';
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);
        $response = null;
        $finish = function (string $status, string $reason = '') use ($target, $options, $startedAt, &$response): ScanResult {
            return new ScanResult($target, $this->category(), $this->siteName(), $this->siteUrl(),
                $status, $reason, mode: $this->mode(), key: $this->key(),
                metadata: $this->mergeRequestDiagnostics([], $options, $response, $startedAt));
        };

        try {
            // Upstream parity: JSON payload and a strict `exists` boolean check.
            // Never text-match true/false anywhere in the body.
            $request = Http::asJson()->acceptJson()->timeout(10)->withOptions([
                'verify' => (bool) config('scanner.verify_ssl', false),
            ]);

            if (!empty($options['proxy'])) {
                $request = $request->withOptions(['proxy' => $options['proxy']]);
            }

            $response = $request->post('https://api.accounts.firefox.com/v1/account/status', [
                'email' => $target,
            ]);

            if ($response->status() === 403) {
                return $finish('Error', 'Caught by WAF or IP Block (403)');
            }
            if ($response->status() === 429) {
                return $finish('Error', 'Rate limited by Firefox (429)');
            }
            if ($response->status() !== 200) {
                return $finish('Error', 'HTTP Error: ' . $response->status());
            }

            $data = $response->json();
            if (!is_array($data)) {
                return $finish('Error', 'Unexpected response body format');
            }

            $exists = $data['exists'] ?? null;
            if ($exists === true) {
                return $finish('Registered');
            }
            if ($exists === false) {
                return $finish('Not Registered');
            }

            return $finish('Error', 'Unexpected response body structure');
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
}
