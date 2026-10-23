<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use Illuminate\Support\Facades\Log;
use Throwable;

/** Never pass exception objects, serialized jobs, HTTP options or targets to logs. */
final class ScannerDiagnostics
{
    public static function exception(Throwable $exception, string $phase, array $context = []): string
    {
        $message = trim(self::redact($exception->getMessage(), $context['redact_values'] ?? []));
        if ($message === '') {
            $message = class_basename($exception).' during '.$phase;
        }
        self::event('exception', $phase, $context + [
            'exception_class' => $exception::class, 'message' => $message,
        ], 'error');

        return $message;
    }

    public static function event(string $event, string $phase, array $context = [], string $level = 'warning'): void
    {
        $safe = ['event' => $event, 'phase' => $phase];
        foreach (['run_id', 'mode', 'module', 'target_hash', 'proxy_id', 'exception_class', 'message', 'attempt'] as $key) {
            if (isset($context[$key]) && is_scalar($context[$key])) {
                // Identifiers/classes are schema-controlled, not raw targets.
                // Redacting a one-character username in them destroys context.
                $safe[$key] = self::redact((string) $context[$key], $key === 'message' ? ($context['redact_values'] ?? []) : []);
            }
        }
        try {
            Log::log($level, 'Scanner diagnostic', $safe);
        } catch (Throwable) {
            // A broken log backend must not lose a result or prevent release.
        }
    }

    public static function redact(string $message, array $sensitive = []): string
    {
        foreach ($sensitive as $secret) {
            if (is_string($secret) && $secret !== '') {
                $message = str_replace(array_unique([$secret, rawurlencode($secret), urlencode($secret)]), '[TARGET]', $message);
            }
        }
        foreach (['username', 'password'] as $key) {
            $secret = (string) config('scanner.proxies.credentials.'.$key, '');
            if ($secret !== '') {
                $message = str_replace(array_unique([$secret, rawurlencode($secret), urlencode($secret)]), '[REDACTED]', $message);
            }
        }
        $message = preg_replace('~https?://[^\s<>]+~i', '[URL]', $message) ?? '[unreadable message]';
        $message = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[EMAIL]', $message) ?? '[unreadable message]';

        return mb_substr($message, 0, 600);
    }
}
