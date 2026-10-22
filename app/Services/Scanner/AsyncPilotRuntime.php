<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use Closure;
use Fiber;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;
use WeakMap;

/**
 * Experimental, single-process cooperative I/O. Only explicitly supplied tasks
 * enter this context; ordinary queue workers retain their synchronous behavior.
 * Validators, proxy policy, enrichment and durable job settlement are reused.
 */
final class AsyncPilotRuntime
{
    /** @var WeakMap<Fiber, self>|null */
    private static ?WeakMap $contexts = null;

    private CurlMultiHandler $handler;

    private array $active = [];

    private array $ready = [];

    private int $generation = 0;

    public function __construct()
    {
        $this->handler = new CurlMultiHandler(['select_timeout' => 0.01]);
    }

    public static function current(): ?self
    {
        $fiber = Fiber::getCurrent();

        return $fiber === null ? null : (self::$contexts[$fiber] ?? null);
    }

    public static function get(PendingRequest $request, string $url, ?array $query = null): Response
    {
        $runtime = self::current();
        if ($runtime === null) {
            return $query === null ? $request->get($url) : $request->get($url, $query);
        }

        $runtime->checkDeadline();

        $request = $request->setHandler($runtime->handler)->async();
        $promise = $query === null ? $request->get($url) : $request->get($url, $query);
        // Laravel 12 defers sending until its LazyPromise is explicitly built.
        // Waiting here would serialize the requests; keep the underlying
        // cancellable Guzzle promise and advance it with the shared multi loop.
        if ($promise instanceof LazyPromise) {
            $promise = $promise->buildPromise();
        }
        $response = $runtime->await($promise);
        // Laravel's async failure path can FULFILL with a Throwable instead of
        // rejecting. Match synchronous HTTP exception semantics exactly.
        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }

    public static function sleep(float $seconds): void
    {
        $runtime = self::current();
        if ($runtime === null) {
            usleep((int) ($seconds * 1_000_000));

            return;
        }

        $runtime->checkDeadline();
        $id = spl_object_id(Fiber::getCurrent());
        $runtime->active[$id]['wake'] = microtime(true) + $seconds;
        Fiber::suspend();
    }

    /**
     * The producer is called whenever capacity opens. Returning null means no
     * work is currently available. It is re-polled while other jobs are active,
     * allowing the existing fair scheduler to replenish without batch barriers.
     *
     * @param  callable(): ?Closure  $next
     * @return array{completed:int, peak_tasks:int, elapsed_seconds:float}
     */
    public function run(callable $next, int $concurrency, float $jobTimeout = 60): array
    {
        if ($concurrency < 1 || $concurrency > 60 || $jobTimeout <= 0 || $this->active !== []) {
            throw new RuntimeException('Invalid async pilot concurrency, deadline, or nested execution.');
        }
        if (! extension_loaded('curl')) {
            throw new RuntimeException('The async pilot requires ext-curl.');
        }

        $started = microtime(true);
        $completed = 0;
        $peak = 0;
        self::$contexts ??= new WeakMap;

        try {
            do {
                while (count($this->active) < $concurrency && ($task = $next()) !== null) {
                    $fiber = new Fiber($task);
                    $id = spl_object_id($fiber);
                    $this->active[$id] = [
                        'fiber' => $fiber, 'promise' => null, 'sequence' => 0,
                        'wake' => null, 'deadline' => microtime(true) + $jobTimeout, 'expired' => false,
                    ];
                    self::$contexts[$fiber] = $this;
                    $peak = max($peak, count($this->active));
                    $fiber->start();
                    $this->settle($id, $completed);
                }

                if ($this->active === []) {
                    break;
                }
                $this->handler->tick();
                Utils::queue()->run();

                // Never resume a Fiber inside a promise callback: a callback
                // can itself initiate another HTTP request or acquire a lease.
                $ready = $this->ready;
                $this->ready = [];
                foreach ($ready as [$id, $sequence, $ok, $value]) {
                    if (! isset($this->active[$id]) || $this->active[$id]['sequence'] !== $sequence) {
                        continue;
                    }
                    $fiber = $this->active[$id]['fiber'];
                    $this->active[$id]['promise'] = null;
                    if ($ok) {
                        $fiber->resume($value);
                    } else {
                        $fiber->throw($value instanceof Throwable ? $value : new RuntimeException((string) $value));
                    }
                    $this->settle($id, $completed);
                }

                foreach (array_keys($this->active) as $id) {
                    $slot = $this->active[$id];
                    if (microtime(true) >= $slot['deadline']) {
                        $this->active[$id]['expired'] = true;
                        $this->active[$id]['deadline'] = INF;
                        $this->active[$id]['sequence'] = ++$this->generation;
                        $slot['promise']?->cancel();
                        $this->active[$id]['promise'] = null;
                        $this->active[$id]['wake'] = null;
                        $slot['fiber']->throw(new RuntimeException('Async pilot job timed out'));
                        $this->settle($id, $completed);
                    } elseif ($slot['wake'] !== null && microtime(true) >= $slot['wake']) {
                        $this->active[$id]['wake'] = null;
                        $slot['fiber']->resume();
                        $this->settle($id, $completed);
                    }
                }
                // Avoid busy-spinning while all tasks are waiting for leases.
                if ($this->ready === [] && $this->active !== []
                    && array_filter($this->active, static fn (array $slot): bool => $slot['promise'] !== null) === []) {
                    usleep(1000);
                }
            } while (true);
        } finally {
            foreach ($this->active as $slot) {
                $slot['promise']?->cancel();
                unset(self::$contexts[$slot['fiber']]);
            }
            $this->active = [];
            $this->ready = [];
        }

        return ['completed' => $completed, 'peak_tasks' => $peak, 'elapsed_seconds' => microtime(true) - $started];
    }

    private function await(PromiseInterface $promise): mixed
    {
        $id = spl_object_id(Fiber::getCurrent());
        // Object IDs can be reused after a completed Fiber is destroyed. A
        // runtime-wide generation prevents a late cancellation callback from
        // being delivered to a newer task that happens to reuse the same ID.
        $sequence = ++$this->generation;
        $this->active[$id]['sequence'] = $sequence;
        $this->active[$id]['promise'] = $promise;
        $promise->then(
            function ($response) use ($id, $sequence): void {
                $this->ready[] = [$id, $sequence, true, $response];
            },
            function ($error) use ($id, $sequence): void {
                $this->ready[] = [$id, $sequence, false, $error];
            },
        );

        return Fiber::suspend();
    }

    private function checkDeadline(): void
    {
        $id = spl_object_id(Fiber::getCurrent());
        if ($this->active[$id]['expired']) {
            throw new RuntimeException('Async pilot job timed out');
        }
    }

    private function settle(int $id, int &$completed): void
    {
        $fiber = $this->active[$id]['fiber'];
        if ($fiber->isTerminated()) {
            unset(self::$contexts[$fiber], $this->active[$id]);
            $completed++;
        }
    }
}
