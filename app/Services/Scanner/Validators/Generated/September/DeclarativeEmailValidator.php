<?php

declare(strict_types=1);

namespace App\Services\Scanner\Validators\Generated\September;

use App\DTO\ScanResult;
use App\Services\Scanner\Validators\Generated\BaseGeneratedValidator;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Executes the declarative email probes imported from user-scanner v1.5.2.
 *
 * Definitions are deliberately data-only so they remain compatible with
 * `artisan config:cache` and can be audited against the Python source.
 */
final class DeclarativeEmailValidator extends BaseGeneratedValidator
{
    /** @param array<string, mixed> $definition */
    public function __construct(private readonly array $definition) {}

    public function key(): string
    {
        return (string) $this->definition['key'];
    }

    public function category(): string
    {
        return (string) $this->definition['category'];
    }

    public function mode(): string
    {
        return 'email';
    }

    public function siteName(): string
    {
        return (string) $this->definition['site_name'];
    }

    public function siteUrl(): string
    {
        return (string) $this->definition['site_url'];
    }

    public function check(string $target, array $options = []): ScanResult
    {
        $startedAt = microtime(true);

        try {
            $context = [
                'target' => $target,
                'uuid' => (string) Str::uuid(),
                'uuid2' => (string) Str::uuid(),
                'uuid3' => (string) Str::uuid(),
                'firebase_uuid' => $this->firebaseUuid(),
                'hex16' => bin2hex(random_bytes(8)),
                'hex22' => bin2hex(random_bytes(11)),
                'hex23' => bin2hex(random_bytes(11)).'a',
                'hex30' => bin2hex(random_bytes(15)),
                'hex32' => bin2hex(random_bytes(16)),
                'hex32_upper' => strtoupper(bin2hex(random_bytes(16))),
                'hex33' => substr(bin2hex(random_bytes(17)), 0, 33),
                'digits19' => (string) random_int(1000000000000000000, PHP_INT_MAX),
                'digits12' => (string) random_int(100000000000, 999999999999),
                'digits7' => (string) random_int(1000000, 9999999),
                'anonymous_id' => bin2hex(random_bytes(6)).'.0136',
                'epoch_ms' => (string) ((int) floor(microtime(true) * 1000)),
                'minute' => (string) ((int) floor(time() / 60)),
                'amz_date' => gmdate('Ymd\\THis\\Z'),
                'time_plus0530' => gmdate('Y-m-d H:i:s').' +0530',
            ];
            $domain = str_contains($target, '@') ? (string) substr(strrchr($target, '@'), 1) : 'example.com';
            $context['probe_target'] = bin2hex(random_bytes(16)).'@'.$domain;
            $context['linga_signature'] = $this->lingaSignature($target, $context['minute']);
            $request = (array) ($this->definition['request'] ?? []);
            $cookieJar = new CookieJar;

            $backoff = (array) ($this->definition['backoff'] ?? []);
            $gate = $this->throttleBackoffGate($target, $backoff);
            if ($gate !== null) {
                return $this->result(
                    target: $target,
                    status: 'Error',
                    reason: $gate['reason'],
                    metadata: $gate['metadata'],
                    options: $options,
                    startedAt: $startedAt,
                );
            }

            foreach ((array) ($this->definition['bootstrap'] ?? []) as $bootstrap) {
                if (! is_array($bootstrap)) {
                    continue;
                }
                $bootstrapResponse = $this->send($bootstrap, $context, $options, $cookieJar);
                $challenge = $this->detectBlockedOrChallenged($bootstrapResponse);
                if ($challenge !== null) {
                    return $this->result($target, $challenge[0], $challenge[1], options: $options, response: $bootstrapResponse, startedAt: $startedAt);
                }
                $bootstrapJson = $this->decodeResponseJson($bootstrapResponse, false);
                $expect = $this->interpolateValue((array) ($bootstrap['expect'] ?? ['field' => 'status', 'value' => 200]), $context);
                if (! $this->matches($expect, $bootstrapResponse, $bootstrapJson)) {
                    return $this->result(
                        $target,
                        'Error',
                        (string) ($bootstrap['error_reason'] ?? 'September workflow bootstrap response was unexpected'),
                        $this->diagnosticMetadata($bootstrapResponse, $target, $options),
                        $options,
                        $bootstrapResponse,
                        $startedAt,
                    );
                }
                foreach ((array) ($bootstrap['capture'] ?? []) as $name => $capture) {
                    if (! is_array($capture)) {
                        continue;
                    }
                    try {
                        $context[(string) $name] = $this->captureValue($capture, $bootstrapResponse, $bootstrapJson, $cookieJar);
                    } catch (RuntimeException $e) {
                        return $this->result(
                            $target,
                            'Error',
                            $e->getMessage(),
                            $this->diagnosticMetadata($bootstrapResponse, $target, $options),
                            $options,
                            $bootstrapResponse,
                            $startedAt,
                        );
                    }
                }
                foreach ((array) ($bootstrap['stop_rules'] ?? []) as $rule) {
                    $condition = $this->interpolateValue((array) ($rule['when'] ?? []), $context);
                    if (is_array($rule) && $this->matches($condition, $bootstrapResponse, $bootstrapJson)) {
                        return $this->result($target, (string) ($rule['status'] ?? 'Error'), (string) ($rule['reason'] ?? ''), $this->resolveMetadata($rule, $bootstrapJson, $context), $options, $bootstrapResponse, $startedAt);
                    }
                }
            }

            $response = $this->send($request, $context, $options, $cookieJar);

            $semanticStatuses = array_map('intval', (array) ($this->definition['semantic_statuses'] ?? []));
            if (! in_array($response->status(), $semanticStatuses, true)) {
                $challenge = $this->detectBlockedOrChallenged($response);
                if ($challenge !== null) {
                    return $this->result(
                        target: $target,
                        status: $challenge[0],
                        reason: $challenge[1],
                        options: $options,
                        response: $response,
                        startedAt: $startedAt,
                    );
                }
            }

            $json = $this->decodeResponseJson($response, (bool) ($this->definition['json_from_braces'] ?? false));
            $this->recordThrottleBackoff($target, $backoff, $response, $json);
            foreach ((array) ($this->definition['rules'] ?? []) as $rule) {
                $condition = $this->interpolateValue((array) ($rule['when'] ?? []), $context);
                if (! is_array($rule) || ! $this->matches($condition, $response, $json)) {
                    continue;
                }

                if (($rule['status'] ?? null) === 'Probe') {
                    return $this->runProbe($target, $context, $options, $cookieJar, $startedAt);
                }

                $metadata = $this->resolveMetadata($rule, $json, $context);
                $metadata = $this->resolveOptionalFollowupMetadata($rule, $metadata, $context, $options, $cookieJar);
                $reason = ! empty($rule['optional_followup']) && ! empty($options[(string) ($rule['optional_followup']['option'] ?? '')])
                    ? (string) ($rule['followup_reason'] ?? '')
                    : (string) ($rule['reason'] ?? '');

                return $this->result(
                    target: $target,
                    status: (string) ($rule['status'] ?? 'Error'),
                    reason: $this->interpolate($reason, $context + ['http_status' => $response->status()]),
                    metadata: $metadata,
                    options: $options,
                    response: $response,
                    startedAt: $startedAt,
                );
            }

            return $this->result(
                target: $target,
                status: 'Error',
                reason: $this->interpolate(
                    (string) ($this->definition['unexpected_reason'] ?? 'Unexpected response from {key} (HTTP {http_status})'),
                    $context + ['key' => $this->key(), 'http_status' => $response->status()],
                ),
                metadata: $this->diagnosticMetadata($response, $target, $options),
                options: $options,
                response: $response,
                startedAt: $startedAt,
            );
        } catch (Throwable $e) {
            return $this->result(
                target: $target,
                status: 'Error',
                reason: $e->getMessage(),
                options: $options,
                startedAt: $startedAt,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, scalar>  $context
     * @param  array<string, mixed>  $options
     */
    private function send(array $request, array $context, array $options, CookieJar $cookieJar): Response
    {
        $method = strtoupper((string) ($request['method'] ?? 'GET'));
        $url = $this->interpolate((string) ($request['url'] ?? ''), $context);
        $headers = $this->interpolateValue((array) ($request['headers'] ?? []), $context);
        $query = $this->interpolateValue((array) ($request['query'] ?? []), $context);
        $body = $this->interpolateValue($request['body'] ?? [], $context);
        $timeout = max(1, (int) ($request['timeout'] ?? 15));

        $pending = Http::timeout($timeout)
            ->withOptions([
                'allow_redirects' => (bool) ($request['follow_redirects'] ?? true),
                'verify' => (bool) config('scanner.verify_ssl', false),
                'cookies' => $cookieJar,
            ])
            ->withHeaders(array_merge([
                'User-Agent' => (string) config('scanner.user_agent'),
                'Accept' => 'application/json,text/plain,*/*;q=0.8',
            ], $headers));

        if (! empty($options['proxy'])) {
            $pending = $pending->withOptions(['proxy' => $options['proxy']]);
        }
        if ($query !== [] && $method !== 'GET') {
            $pending = $pending->withOptions(['query' => $query]);
        }

        if ($method === 'GET') {
            return $pending->get($url, $query);
        }

        if ($method === 'POST') {
            return $this->post($pending, $url, $body, (string) ($request['body_mode'] ?? 'json'), $headers);
        }

        return $pending->send($method, $url, [
            'query' => $query,
            'json' => is_array($body) ? $body : null,
        ]);
    }

    /** @param array<string, mixed>|string $body @param array<string, string> $headers */
    private function post(PendingRequest $pending, string $url, array|string $body, string $bodyMode, array $headers): Response
    {
        if ($bodyMode === 'form') {
            return $pending->asForm()->post($url, is_array($body) ? $body : []);
        }
        if ($bodyMode === 'raw') {
            $contentType = (string) ($headers['Content-Type'] ?? $headers['content-type'] ?? 'text/plain');

            return $pending->withBody(is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR), $contentType)->post($url);
        }

        return $pending->post($url, is_array($body) ? $body : []);
    }

    /** @return array<string, mixed>|array<int, mixed>|null */
    private function decodeResponseJson(Response $response, bool $fromBraces): ?array
    {
        try {
            if ($fromBraces) {
                $body = $response->body();
                $start = strpos($body, '{');
                $end = strrpos($body, '}');
                if ($start === false || $end === false || $end < $start) {
                    return null;
                }
                $decoded = json_decode(substr($body, $start, $end - $start + 1), true, flags: JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : null;
            }
            $decoded = $response->json();

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $condition @param array<string, mixed>|array<int, mixed>|null $json */
    private function matches(array $condition, Response $response, ?array $json): bool
    {
        if ($condition === []) {
            return true;
        }
        if (isset($condition['all'])) {
            foreach ((array) $condition['all'] as $child) {
                if (! is_array($child) || ! $this->matches($child, $response, $json)) {
                    return false;
                }
            }

            return true;
        }
        if (isset($condition['any'])) {
            foreach ((array) $condition['any'] as $child) {
                if (is_array($child) && $this->matches($child, $response, $json)) {
                    return true;
                }
            }

            return false;
        }
        if (isset($condition['not']) && is_array($condition['not'])) {
            return ! $this->matches($condition['not'], $response, $json);
        }

        $field = (string) ($condition['field'] ?? 'status');
        $actual = match ($field) {
            'status' => $response->status(),
            'body' => $response->body(),
            'header' => $response->header((string) ($condition['name'] ?? '')),
            'json' => $json === null ? null : data_get($json, (string) ($condition['path'] ?? '')),
            'json_root' => $json,
            default => null,
        };
        $transform = (string) ($condition['transform'] ?? '');
        if ($transform === 'trim' && is_string($actual)) {
            $actual = trim($actual);
        } elseif ($transform === 'lower' && is_string($actual)) {
            $actual = strtolower($actual);
        }

        $expected = $condition['value'] ?? null;

        return match ((string) ($condition['operator'] ?? 'eq')) {
            'eq' => $actual === $expected,
            'neq' => $actual !== $expected,
            'in' => in_array($actual, (array) $expected, true),
            'contains' => $this->contains($actual, $expected, false),
            'contains_ci' => $this->contains($actual, $expected, true),
            'truthy' => $actual === true,
            'falsy' => $actual === false,
            'exists' => $actual !== null,
            'nonempty' => is_array($actual) ? $actual !== [] : (is_string($actual) && $actual !== ''),
            'empty' => $actual === null || $actual === '' || $actual === [],
            'count_gt' => is_countable($actual) && count($actual) > (int) $expected,
            'is_list' => is_array($actual) && array_is_list($actual),
            // Associative decoding otherwise collapses JSON {} and [] into
            // the same PHP value. Use only for an explicit root-list contract.
            'is_empty_list' => $field === 'json_root' && $actual === []
                && is_array(json_decode($response->body())),
            'array_any' => is_array($actual) && $this->arrayAny($actual, (array) ($condition['where'] ?? []), $response, $json),
            'array_contains_ci' => is_array($actual) && $this->arrayContains($actual, $expected, true),
            default => false,
        };
    }

    private function contains(mixed $actual, mixed $expected, bool $caseInsensitive): bool
    {
        if (is_array($actual)) {
            return in_array($expected, $actual, true);
        }
        if (! is_scalar($actual) || ! is_scalar($expected)) {
            return false;
        }
        $haystack = (string) $actual;
        $needle = (string) $expected;

        return $caseInsensitive
            ? str_contains(strtolower($haystack), strtolower($needle))
            : str_contains($haystack, $needle);
    }

    /** @param array<array-key, mixed> $actual */
    private function arrayContains(array $actual, mixed $expected, bool $caseInsensitive): bool
    {
        foreach ($actual as $item) {
            if (is_array($item) && $this->arrayContains($item, $expected, $caseInsensitive)) {
                return true;
            }
            if ($this->contains($item, $expected, $caseInsensitive)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $items @param array<string, mixed> $where @param array<string, mixed>|array<int, mixed>|null $json */
    private function arrayAny(array $items, array $where, Response $response, ?array $json): bool
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $local = ['item' => $item, 'root' => $json];
            $condition = $this->replaceJsonRoot($where, $local);
            if ($this->matches($condition, $response, $local)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $condition @param array<string, mixed> $local @return array<string, mixed> */
    private function replaceJsonRoot(array $condition, array $local): array
    {
        if (($condition['field'] ?? null) === 'item') {
            $condition['field'] = 'json';
            $condition['path'] = 'item.'.(string) ($condition['path'] ?? '');
        }
        foreach (['all', 'any'] as $group) {
            if (! isset($condition[$group]) || ! is_array($condition[$group])) {
                continue;
            }
            $condition[$group] = array_map(
                fn (mixed $child): mixed => is_array($child) ? $this->replaceJsonRoot($child, $local) : $child,
                $condition[$group],
            );
        }

        return $condition;
    }

    /** @param array<string, mixed> $rule @param array<string, mixed>|array<int, mixed>|null $json @param array<string, scalar> $context @return array<string, mixed> */
    private function resolveMetadata(array $rule, ?array $json, array $context): array
    {
        $metadata = $this->interpolateValue((array) ($rule['metadata'] ?? []), $context);
        if ($json === null) {
            return $metadata;
        }
        foreach ((array) ($rule['metadata_from_json'] ?? []) as $name => $path) {
            $value = data_get($json, (string) $path);
            if ($value !== null && $value !== false && $value !== '' && $value !== []) {
                $metadata[(string) $name] = $value;
            }
        }

        return $metadata;
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function diagnosticMetadata(Response $response, string $target, array $options): array
    {
        if (! (bool) ($options['diagnostic_response'] ?? false)) {
            return [];
        }

        $body = preg_replace('/\s+/', ' ', trim($response->body())) ?? '';
        $body = str_ireplace(
            array_unique([$target, rawurlencode($target), urlencode($target), json_encode($target, JSON_THROW_ON_ERROR)]),
            '[target]',
            $body,
        );

        return [
            'diagnostic_content_type' => $response->header('Content-Type'),
            'diagnostic_body_sha256' => hash('sha256', $response->body()),
            'diagnostic_body_excerpt' => mb_substr($body, 0, 1200),
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $metadata
     * @param  array<string, scalar>  $context
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function resolveOptionalFollowupMetadata(
        array $rule,
        array $metadata,
        array $context,
        array $options,
        CookieJar $cookieJar,
    ): array {
        $followup = $rule['optional_followup'] ?? null;
        if (! is_array($followup)) {
            return $metadata;
        }

        $option = (string) ($followup['option'] ?? '');
        if ($option === '' || empty($options[$option]) || ! is_array($followup['request'] ?? null)) {
            return $metadata;
        }

        try {
            $followupContext = $context;
            $followupContext['epoch_ms'] = (string) ((int) floor(microtime(true) * 1000));
            $response = $this->send($followup['request'], $followupContext, $options, $cookieJar);
            $acceptedStatuses = array_map('intval', (array) ($followup['semantic_statuses'] ?? [200]));
            if (! in_array($response->status(), $acceptedStatuses, true)) {
                return $metadata;
            }

            $json = $this->decodeResponseJson($response, false);
            if ($json === null) {
                return $metadata;
            }
            foreach ((array) ($followup['metadata_from_json'] ?? []) as $name => $path) {
                $value = data_get($json, (string) $path);
                if ($value !== null && $value !== false && $value !== '' && $value !== []) {
                    $metadata[(string) $name] = $value;
                }
            }
        } catch (Throwable) {
            // The primary silent account result remains valid if optional enrichment fails.
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $options
     */
    private function result(
        string $target,
        string $status,
        string $reason = '',
        array $metadata = [],
        array $options = [],
        ?Response $response = null,
        ?float $startedAt = null,
    ): ScanResult {
        $metadata = $this->mergeRequestDiagnostics($metadata, $options, $response, $startedAt);

        return new ScanResult(
            target: $target,
            category: $this->category(),
            siteName: $this->siteName(),
            url: $this->siteUrl(),
            status: $status,
            reason: $reason,
            extra: $this->metadataSummary($metadata),
            mode: 'email',
            key: $this->key(),
            metadata: $metadata,
            error: $status === 'Error' ? $reason : null,
        );
    }

    /** @param array<string, scalar> $context @param array<string, mixed> $options */
    private function runProbe(
        string $target,
        array $context,
        array $options,
        CookieJar $cookieJar,
        float $startedAt,
    ): ScanResult {
        $request = (array) ($this->definition['probe_request'] ?? $this->definition['request'] ?? []);
        $response = $this->send($request, $context, $options, $cookieJar);
        $challenge = $this->detectBlockedOrChallenged($response);
        if ($challenge !== null) {
            return $this->result($target, $challenge[0], $challenge[1], options: $options, response: $response, startedAt: $startedAt);
        }
        $json = $this->decodeResponseJson($response, false);
        foreach ((array) ($this->definition['probe_rules'] ?? []) as $rule) {
            $condition = $this->interpolateValue((array) ($rule['when'] ?? []), $context);
            if (! is_array($rule) || ! $this->matches($condition, $response, $json)) {
                continue;
            }

            return $this->result(
                $target,
                (string) ($rule['status'] ?? 'Error'),
                (string) ($rule['reason'] ?? ''),
                $this->resolveMetadata($rule, $json, $context),
                $options,
                $response,
                $startedAt,
            );
        }

        return $this->result($target, 'Error', 'Domain-control probe returned an unexpected response', options: $options, response: $response, startedAt: $startedAt);
    }

    /**
     * Bounded service/repeated-target backoff for modules whose API returns an
     * explicit throttle signal (e.g. Talkme errCode 80820058). While a cooldown
     * is active the module returns an Error without any network request; both
     * windows expire automatically, so this is never a permanent skip.
     *
     * @param  array<string, mixed>  $backoff
     * @return array{reason:string,metadata:array<string, mixed>}|null
     */
    private function throttleBackoffGate(string $target, array $backoff): ?array
    {
        if ($backoff === []) {
            return null;
        }

        $now = time();
        foreach (['target' => $this->backoffTargetCacheKey($target), 'service' => $this->backoffServiceCacheKey()] as $scope => $cacheKey) {
            $until = (int) Cache::get($cacheKey, 0);
            if ($until <= $now) {
                continue;
            }

            $remaining = $until - $now;

            return [
                'reason' => $this->key().' explicit throttle backoff active ('.$scope.' scope); retry after '.$remaining.'s',
                'metadata' => [
                    'throttle_backoff' => $scope,
                    'retry_after_seconds' => $remaining,
                ],
            ];
        }

        return null;
    }

    /** @param array<string, mixed> $backoff @param array<string, mixed>|array<int, mixed>|null $json */
    private function recordThrottleBackoff(string $target, array $backoff, Response $response, ?array $json): void
    {
        $match = $backoff['match'] ?? null;
        if (! is_array($match) || $match === []) {
            return;
        }

        try {
            $matched = $this->matches($this->interpolateValue($match, ['target' => $target]), $response, $json);
        } catch (Throwable) {
            return;
        }
        if (! $matched) {
            return;
        }

        $now = time();
        $targetCooldown = $this->boundedCooldown($backoff['target_cooldown_seconds'] ?? 300);
        $serviceCooldown = $this->boundedCooldown($backoff['service_cooldown_seconds'] ?? 60);
        if ($targetCooldown > 0) {
            Cache::put($this->backoffTargetCacheKey($target), $now + $targetCooldown, $targetCooldown + 60);
        }
        if ($serviceCooldown > 0) {
            Cache::put($this->backoffServiceCacheKey(), $now + $serviceCooldown, $serviceCooldown + 60);
        }
    }

    private function boundedCooldown(mixed $seconds): int
    {
        return max(0, min(3600, (int) $seconds));
    }

    private function backoffTargetCacheKey(string $target): string
    {
        return 'scanner:email-backoff:'.$this->key().':target:'.hash('sha256', strtolower($target));
    }

    private function backoffServiceCacheKey(): string
    {
        return 'scanner:email-backoff:'.$this->key().':service';
    }

    /** @param array<string, scalar> $context */
    private function interpolate(string $value, array $context): string
    {
        foreach ($context as $name => $replacement) {
            $value = str_replace('{'.$name.'}', (string) $replacement, $value);
            if ($name === 'target') {
                $value = str_replace('{target_url}', rawurlencode((string) $replacement), $value);
                $value = str_replace('{target_json}', trim(json_encode((string) $replacement, JSON_THROW_ON_ERROR), '"'), $value);
            }
        }

        return $value;
    }

    /** @param array<string, scalar> $context */
    private function interpolateValue(mixed $value, array $context): mixed
    {
        if (is_string($value)) {
            return $this->interpolate($value, $context);
        }
        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->interpolateValue($item, $context);
        }

        return $result;
    }

    private function firebaseUuid(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    /** @param array<string, mixed> $capture @param array<string, mixed>|array<int, mixed>|null $json */
    private function captureValue(array $capture, Response $response, ?array $json, CookieJar $cookieJar): string
    {
        $source = (string) ($capture['from'] ?? 'body_regex');
        $value = null;
        if ($source === 'body_regex') {
            $pattern = (string) ($capture['pattern'] ?? '');
            if ($pattern !== '' && preg_match($pattern, $response->body(), $matches) === 1) {
                $value = $matches[(int) ($capture['group'] ?? 1)] ?? null;
            }
        } elseif ($source === 'json') {
            $value = $json === null ? null : data_get($json, (string) ($capture['path'] ?? ''));
        } elseif ($source === 'header') {
            $value = $response->header((string) ($capture['name'] ?? ''));
        } elseif ($source === 'header_regex') {
            $header = (string) $response->header((string) ($capture['name'] ?? ''));
            $pattern = (string) ($capture['pattern'] ?? '');
            if ($pattern !== '' && preg_match($pattern, $header, $matches) === 1) {
                $value = $matches[(int) ($capture['group'] ?? 1)] ?? null;
            }
        } elseif ($source === 'cookie') {
            $value = $cookieJar->getCookieByName((string) ($capture['name'] ?? ''))?->getValue();
        }
        if (! is_scalar($value) || (string) $value === '') {
            throw new RuntimeException((string) ($capture['error_reason'] ?? 'Required workflow token could not be extracted'));
        }
        $result = (string) $value;
        if (($capture['transform'] ?? null) === 'html_decode') {
            $result = html_entity_decode($result, ENT_QUOTES | ENT_HTML5);
        }

        return $result;
    }

    private function lingaSignature(string $email, string $timestamp): string
    {
        $body = json_encode(['email' => $email], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $bodyHash = hash('sha256', $body);
        $signingKey = 'UDNuWUUwaXplRzdEWHVOTEg4TkJVOGJMZXBGemE0VnczS2svMXg5ZnZVM2ZQVXEyd1FzbGNFQ1ZhSm1tMVJ0agpKT1VVZ3FyNTB3c3hvU05Ub3M1SWFyL0V5ajgyellZcncwR3dkYWl0djA1ZklLNi8vQUY2WC9kYTdqZkRWSjYrCnovdUNDZz09';
        $value = 'web_3.5.2/275_'.$signingKey.'_scanner_'.$timestamp.'__POST_/recovery_'.$bodyHash;
        $value = strrev(base64_encode($value));
        $value = substr($value, 0, 5).'XYZ'.substr($value, 5);
        $value = substr($value, 3).substr($value, 0, 3);
        $value = strtr($value, 'aeiouAEIOU', 'XXXXXXXXXX');
        $half = intdiv(strlen($value), 2);

        return hash('sha256', substr($value, $half).substr($value, 0, $half));
    }
}
