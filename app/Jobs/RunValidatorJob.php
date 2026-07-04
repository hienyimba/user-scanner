<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTO\ScanResult;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\ScannerEngineService;
use App\Support\ScanRunStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RunValidatorJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public int $tries;

    /**
     * @param array<string, mixed> $validatorMeta
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $mode,
        public readonly string $validatorKey,
        public readonly array $validatorMeta,
        public readonly string $target,
        public readonly int $targetIndex,
        public readonly int $validatorIndex,
        public readonly array $options = [],
        public readonly int $proxyOffset = 0,
    ) {
        $this->timeout = (int) config('scanner.async.job_timeout', 45);
        $this->tries = (int) config('scanner.async.job_tries', 1);
    }

    public function handle(ScannerEngineService $engine, ProxyManagerService $proxyManager, ScanRunStore $store): void
    {
        $store->markJobStarted($this->runId);

        try {
            $result = $this->runWithProxyPolicy($engine, $proxyManager);
        } catch (Throwable $e) {
            $result = $this->errorResult($e->getMessage());
        }

        $store->appendResult($this->runId, $result->toArray(), $this->targetIndex, $this->validatorIndex);
    }

    public function failed(?\Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        app(ScanRunStore::class)->appendFailedResult(
            $this->runId,
            $this->errorResult($exception->getMessage())->toArray(),
            $this->targetIndex,
            $this->validatorIndex,
        );
    }

    private function runWithProxyPolicy(ScannerEngineService $engine, ProxyManagerService $proxyManager): ScanResult
    {
        if (empty($this->options['use_proxy']) || empty($this->options['proxy_list'])) {
            return $this->attemptValidator($engine, null);
        }

        $proxyManager->loadFromText((string) $this->options['proxy_list']);
        $lease = $proxyManager->acquire($this->proxyOffset);
        if ($lease === null) {
            return $this->errorResult('No proxy capacity available from configured pool');
        }

        $firstResult = $this->attemptValidator($engine, $lease['proxy']);
        if (!$this->isRetryableProxyFailure($firstResult) || $this->maxProxyRetries() < 1) {
            $this->finalizeLease($proxyManager, $lease['raw'], $firstResult);

            return $firstResult;
        }

        $proxyManager->reportFailure($lease['raw']);
        $proxyManager->release($lease['raw']);

        $retryLease = $proxyManager->tierOf($lease['raw']) === 'primary'
            ? $proxyManager->acquirePreferred('fallback', $this->proxyOffset + 1, [$lease['raw']])
            : $proxyManager->acquire($this->proxyOffset + 1, [$lease['raw']]);
        if ($retryLease === null) {
            return $firstResult;
        }

        $retryResult = $this->attemptValidator($engine, $retryLease['proxy']);
        $this->finalizeLease($proxyManager, $retryLease['raw'], $retryResult);

        return $retryResult;
    }

    private function attemptValidator(ScannerEngineService $engine, ?string $proxy): ScanResult
    {
        try {
            return $engine->runPlannedValidator(
                mode: $this->mode,
                validatorKey: $this->validatorKey,
                target: $this->target,
                options: [
                    ...$this->options,
                    'proxy' => $proxy,
                ],
            );
        } catch (Throwable $e) {
            return $this->errorResult($e->getMessage());
        }
    }

    private function finalizeLease(ProxyManagerService $proxyManager, string $rawProxy, ScanResult $result): void
    {
        if ($this->isRetryableProxyFailure($result)) {
            $proxyManager->reportFailure($rawProxy);
        } else {
            $proxyManager->reportSuccess($rawProxy);
        }

        $proxyManager->release($rawProxy);
    }

    private function maxProxyRetries(): int
    {
        return max(0, (int) config('scanner.proxies.behavior.max_retry_per_module', 1));
    }

    private function isRetryableProxyFailure(ScanResult $result): bool
    {
        if ($result->status !== 'Error') {
            return false;
        }

        $reason = strtolower(trim($result->reason));
        if ($reason === '') {
            return false;
        }

        foreach ([
            '403',
            '429',
            'timeout',
            'timed out',
            'forbidden',
            'cloudflare',
            'waf',
            'captcha',
            'ip block',
            'rate limited',
            'ssl_read',
            'unexpected eof',
            'curl error',
            'unexpected response body',
            'invalid api response format',
        ] as $needle) {
            if (str_contains($reason, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function errorResult(string $reason): ScanResult
    {
        return ScanResult::fromArray([
            'target' => $this->target,
            'category' => (string) ($this->validatorMeta['category'] ?? ''),
            'site_name' => (string) ($this->validatorMeta['site_name'] ?? $this->validatorKey),
            'url' => (string) ($this->validatorMeta['url'] ?? ''),
            'status' => 'Error',
            'reason' => $reason,
            'mode' => $this->mode,
            'key' => $this->validatorKey,
        ]);
    }
}
