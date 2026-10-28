<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\ScanResult;
use App\Services\Scanner\BrowserTransportSession;
use App\Services\Scanner\PhpFfiTransport;
use App\Services\Scanner\ProxyExecutionPolicy;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\Validators\Generated\September\DeclarativeEmailValidator;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class BrowserTransportTest extends TestCase
{
    private function response(int $status = 200, array $headers = [], string $body = ''): Response
    {
        return new Response(new PsrResponse($status, $headers, $body));
    }

    public function test_redirect_cookies_are_retained_and_host_only_cookies_do_not_cross_hosts(): void
    {
        $session = new BrowserTransportSession;
        $jar = new CookieJar;
        $hops = [];
        $session->send(['url' => 'https://a.example.test/start', 'allowed_redirect_hosts' => ['b.example.test'],
            'headers' => ['Authorization' => 'Bearer synthetic', 'Cookie' => 'explicit=secret']], $jar,
            function ($hop) use (&$hops) {
                $hops[] = $hop;

                return count($hops) === 1 ? $this->response(302, ['Location' => 'https://b.example.test/final',
                    'sEt-CoOkIe' => ['hostonly=one; Path=/', 'shared=two; Domain=example.test; Path=/']])
                    : $this->response(200, ['Set-Cookie' => 'final=three; Path=/']);
            });
        $this->assertSame('shared=two', $hops[1]['headers']['Cookie']);
        $this->assertArrayNotHasKey('Authorization', $hops[1]['headers']);
        $session->send(['url' => 'https://a.example.test/next'], $jar, function ($hop) {
            $this->assertStringContainsString('hostonly=one', $hop['headers']['Cookie']);
            $this->assertStringNotContainsString('final=three', $hop['headers']['Cookie']);

            return $this->response();
        });
        $session->send(['url' => 'https://sub.a.example.test/next'], $jar, function ($hop) {
            $this->assertSame('shared=two', $hop['headers']['Cookie']);

            return $this->response();
        });
    }

    public function test_cookie_path_secure_deletion_and_prefixes(): void
    {
        $session = new BrowserTransportSession;
        $jar = new CookieJar;
        $session->send(['url' => 'https://example.test/account/start'], $jar, fn () => $this->response(200,
            ['Set-Cookie' => ['default=one', 'secure=two; Secure; Path=/', 'gone=old; Path=/',
                '__Host-invalid=no; Domain=example.test; Secure; Path=/', '__Host-valid=yes; Secure; Path=/',
                'bad=no; Domain=other.test; Path=/']]));
        $session->send(['url' => 'https://example.test/account/next'], $jar, function ($hop) {
            $this->assertStringContainsString('default=one', $hop['headers']['Cookie']);
            $this->assertStringContainsString('__Host-valid=yes', $hop['headers']['Cookie']);
            $this->assertStringNotContainsString('invalid', $hop['headers']['Cookie']);
            $this->assertStringNotContainsString('bad=', $hop['headers']['Cookie']);

            return $this->response(200, ['Set-Cookie' => 'gone=; Max-Age=0; Path=/']);
        });
        $session->send(['url' => 'https://example.test/elsewhere'], $jar, function ($hop) {
            $this->assertStringNotContainsString('default=', $hop['headers']['Cookie']);
            $this->assertStringNotContainsString('gone=', $hop['headers']['Cookie']);

            return $this->response();
        });
    }

    #[DataProvider('redirectCases')]
    public function test_redirect_method_semantics(int $status, string $method): void
    {
        $count = 0;
        (new BrowserTransportSession)->send(['url' => 'https://example.test/start', 'method' => 'POST',
            'body' => 'synthetic', 'body_mode' => 'raw', 'headers' => ['Content-Type' => 'text/plain']], new CookieJar,
            function ($hop) use (&$count, $status, $method) {
                if (++$count === 1) {
                    return $this->response($status, ['Location' => '/next', 'Set-Cookie' => 'step=one; Path=/']);
                }
                $this->assertSame($method, $hop['method']);
                $this->assertSame('step=one', $hop['headers']['Cookie']);
                $this->assertSame($method === 'GET' ? null : 'synthetic', $hop['body']);

                return $this->response();
            });
        $this->assertSame(2, $count);
    }

    public static function redirectCases(): array
    {
        return [[301, 'GET'], [302, 'GET'], [303, 'GET'], [307, 'POST'], [308, 'POST']];
    }

    public function test_cross_origin_preserved_body_redirect_is_refused_before_send(): void
    {
        $this->expectExceptionMessage('Cross-origin');
        (new BrowserTransportSession)->send(['url' => 'https://example.test', 'method' => 'POST', 'body' => 'private',
            'allowed_redirect_hosts' => ['other.test']], new CookieJar,
            fn () => $this->response(307, ['Location' => 'https://other.test']));
    }

    #[DataProvider('badUrls')]
    public function test_url_policy_refuses_unsafe_destinations(string $url): void
    {
        $this->expectException(ConnectionException::class);
        (new BrowserTransportSession)->send(['url' => $url], new CookieJar, function () {
            $this->fail('Unsafe destination reached wire');
        });
    }

    public static function badUrls(): array
    {
        return [['http://example.test'], ['https://user:pass@example.test'], ['https://127.0.0.1'], ['file:///tmp/secret']];
    }

    public function test_expired_deadline_sends_nothing(): void
    {
        $this->expectExceptionMessage('deadline exceeded');
        (new BrowserTransportSession)->send(['url' => 'https://example.test', 'deadline_ns' => hrtime(true) - 1],
            new CookieJar, function () {
                $this->fail('Expired request sent');
            });
    }

    public function test_redirect_to_unapproved_host_is_refused_before_second_hop(): void
    {
        $count = 0;
        try {
            (new BrowserTransportSession)->send(['url' => 'https://example.test'], new CookieJar,
                function () use (&$count) {
                    $count++;

                    return $this->response(302, ['Location' => 'https://outside.test']);
                });
            $this->fail('Unapproved redirect followed');
        } catch (ConnectionException $error) {
            $this->assertStringContainsString('refused', $error->getMessage());
        }
        $this->assertSame(1, $count);
    }

    public function test_redirect_loop_is_bounded(): void
    {
        config(['scanner.browser_transport.max_redirects' => 2]);
        $count = 0;
        try {
            (new BrowserTransportSession)->send(['url' => 'https://example.test'], new CookieJar,
                function () use (&$count) {
                    $count++;

                    return $this->response(302, ['Location' => '/loop']);
                });
            $this->fail('Loop accepted');
        } catch (ConnectionException $error) {
            $this->assertStringContainsString('redirect limit', $error->getMessage());
        }
        $this->assertSame(3, $count);
    }

    public function test_selected_but_unavailable_ffi_fails_closed_without_fallback(): void
    {
        config(['scanner.php_ffi.enabled' => true]);
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        $this->app->instance(PhpFfiTransport::class, new class extends PhpFfiTransport
        {
            public function isAvailable(): bool
            {
                return false;
            }
        });
        $validator = new DeclarativeEmailValidator(collect(config('scanner_september_email.modules'))->firstWhere('key', 'canva'));
        $result = $validator->check('synthetic@example.test');
        $this->assertSame('Error', $result->status);
        $this->assertStringContainsString('FFI transport unavailable', $result->reason);
    }

    public function test_proxy_retry_is_skipped_when_job_cleanup_budget_would_be_exhausted(): void
    {
        config(['scanner.proxies.pool' => [], 'scanner.proxies.behavior.wait_timeout_seconds' => 20,
            'scanner.proxies.behavior.max_retry_per_module' => 1]);
        $manager = new ProxyManagerService;
        $manager->loadFromText("http://proxy.invalid:8001\nhttp://proxy.invalid:8002");
        $count = 0;
        $result = ProxyExecutionPolicy::run($manager, ['_scanner_deadline_ns' => hrtime(true) + 22_100_000_000], 0,
            function () use (&$count) {
                $count++;
                usleep(150_000);

                return ScanResult::fromArray(['status' => 'Error', 'reason' => 'HTTP 403']);
            },
            fn ($reason) => ScanResult::fromArray(['status' => 'Error', 'reason' => $reason]));
        $this->assertSame(1, $count);
        $this->assertSame('Error', $result->status);
        $lease = $manager->acquire();
        $this->assertNotNull($lease);
        $manager->release($lease['raw'], $lease['token']);
    }

    public function test_expired_job_does_not_wait_for_initial_proxy_lease(): void
    {
        config(['scanner.proxies.pool' => [], 'scanner.proxies.behavior.wait_timeout_seconds' => 20]);
        $manager = new ProxyManagerService;
        $manager->loadFromText('http://proxy.invalid:8001');
        $result = ProxyExecutionPolicy::run($manager, ['_scanner_deadline_ns' => hrtime(true) - 1], 0,
            function () {
                $this->fail('Expired job executed');
            },
            fn ($reason) => ScanResult::fromArray(['status' => 'Error', 'reason' => $reason]));
        $this->assertSame('Insufficient job deadline budget for proxy acquisition', $result->reason);
    }

    public function test_redirects_share_one_budget_and_late_responses_are_rejected(): void
    {
        $count = 0;
        $timeouts = [];
        try {
            (new BrowserTransportSession)->send(['url' => 'https://example.test', 'timeout' => .08], new CookieJar,
                function ($hop) use (&$count, &$timeouts) {
                    $timeouts[] = $hop['timeout'];
                    $this->assertLessThanOrEqual($hop['timeout'], $hop['connect_timeout']);
                    usleep(50_000);

                    return ++$count === 1 ? $this->response(302, ['Location' => '/next']) : $this->response();
                });
            $this->fail('Late response accepted');
        } catch (ConnectionException $error) {
            $this->assertStringContainsString('deadline exceeded', $error->getMessage());
        }
        $this->assertCount(2, $timeouts);
        $this->assertLessThan($timeouts[0], $timeouts[1]);
    }

    public function test_transport_error_redacts_raw_and_encoded_targets(): void
    {
        $target = 'private+control@example.test';
        try {
            (new BrowserTransportSession)->send(['url' => 'https://example.test', 'redact_values' => [$target]], new CookieJar,
                function () use ($target) {
                    throw new \RuntimeException($target.' '.rawurlencode($target).' https://user:password@example.test/token');
                });
            $this->fail('No exception');
        } catch (ConnectionException $error) {
            foreach ([$target, rawurlencode($target), 'password', '/token'] as $secret) {
                $this->assertStringNotContainsString($secret, $error->getMessage());
            }
        }
    }

    public function test_ffi_routing_preserves_four_module_verdicts_without_python_or_plain_http(): void
    {
        config(['scanner.php_ffi.enabled' => true]);
        Http::preventStrayRequests();
        $fake = new class extends PhpFfiTransport
        {
            public array $queue = [];

            public array $specs = [];

            public function send(array $spec, CookieJar $jar): Response
            {
                $this->specs[] = $spec;

                return new Response(new PsrResponse(200, [], array_shift($this->queue)));
            }
        };
        $this->app->instance(PhpFfiTransport::class, $fake);
        $cases = [
            'canva' => [['{"B":{"A?":"A"}}'], ['{"B":{"A?":"B"}}']],
            'kandideeri' => [['<meta name="csrf-token" content="token"><form id="registr-form"></form>', 'NOT_UNIQUE_VALUE'],
                ['<meta name="csrf-token" content="token"><form id="registr-form"></form>', '']],
            'threadless' => [['<input name="csrfmiddlewaretoken" value="token">', '{"is_valid":false,"intercom_lead":true}'],
                ['<input name="csrfmiddlewaretoken" value="token">', '{"is_valid":true,"intercom_lead":false}']],
            'redtube' => [['<script>page_params.token = "token"</script>', '{"email":"create_account_failed","error_message":"This email is already registered"}'],
                ['<script>page_params.token = "token"</script>', '{"email":"create_account_passed","error_message":""}']],
        ];
        foreach ($cases as $key => [$positive, $negative]) {
            $validator = new DeclarativeEmailValidator(collect(config('scanner_september_email.modules'))->firstWhere('key', $key));
            foreach ([[$positive, 'Registered'], [$negative, 'Not Registered']] as [$queue, $expected]) {
                $fake->queue = $queue;
                $this->assertSame($expected, $validator->check('synthetic@example.test')->status, $key);
            }
        }
        $this->assertFalse(PhpFfiTransport::selected('username', 'canva'));
        $this->assertFalse(PhpFfiTransport::selected('email', 'quizlet'));
        foreach ($fake->specs as $spec) {
            $this->assertGreaterThan(hrtime(true), $spec['deadline_ns']);
            $this->assertSame(['synthetic@example.test'], $spec['redact_values']);
        }
    }
}
