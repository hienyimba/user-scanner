<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\NativeTransportException;
use App\Services\Scanner\PhpFfiTransport;
use App\Services\Scanner\Validators\Generated\September\DeclarativeEmailValidator;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Raza\PHPImpersonate\Browser\BrowserConfig;
use Tests\TestCase;

/** Every response is an existing offline parser fixture; no service is contacted. */
final class NativeProfileEmailValidatorTest extends TestCase
{
    private QueuedProfileFfiTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        config(['scanner.php_ffi.enabled' => true]);
        $this->transport = new QueuedProfileFfiTransport;
        $this->app->instance(PhpFfiTransport::class, $this->transport);
    }

    #[DataProvider('profileCases')]
    public function test_all_profiled_module_verdicts_use_native_transport(string $key, array $responses, string $status): void
    {
        $this->transport->responses = $responses;
        $result = $this->validator($key)->check('person@example.com', ['proxy' => 'http://proxy.example.test:8080']);
        $this->assertSame($status, $result->status, $key.': '.$result->reason);
        $this->assertCount(count($responses), $this->transport->specs);
        $this->assertSame([], $this->transport->responses);
        foreach ($this->transport->specs as $spec) {
            $this->assertSame('http://proxy.example.test:8080', $spec['proxy']);
            $this->assertSame(['person@example.com'], $spec['redact_values']);
            $this->assertGreaterThan(hrtime(true), $spec['deadline_ns']);
            $this->assertTrue(BrowserConfig::hasConfig($spec['impersonate']), $key.' native browser profile');
        }
        Http::assertNothingSent();
        Process::assertNothingRan();
    }

    public function test_native_fixtures_cover_both_verdicts_for_all_twenty_four_profiles(): void
    {
        $covered = [];
        foreach (self::profileCases() as [$key, $responses, $status]) {
            $covered[$key][$status] = true;
        }
        $profiles = array_values(array_filter(config('scanner_september_email.modules'),
            static fn (array $definition): bool => ! empty($definition['transport_profile'])));
        $this->assertCount(24, $profiles);
        $this->assertCount(count($profiles), $covered);
        foreach ($profiles as $definition) {
            $key = $definition['key'];
            $this->assertTrue(PhpFfiTransport::selected('email', $key), $key);
            $this->assertSame(['Registered' => true, 'Not Registered' => true], $covered[$key], $key);
        }
    }

    public function test_stopping_native_transport_fails_closed_without_http_or_subprocess(): void
    {
        config(['scanner.php_ffi.enabled' => false]);
        $result = $this->validator('figma')->check('person@example.com');
        $this->assertSame('Error', $result->status);
        $this->assertStringContainsString('FFI transport disabled', $result->reason);
        $this->assertSame([], $this->transport->specs);
        Http::assertNothingSent();
        Process::assertNothingRan();
    }

    public function test_native_failure_preserves_safe_diagnostics_without_creating_a_verdict(): void
    {
        $this->transport->failure = new NativeTransportException('Native connection failed',
            ['curl_code' => 7, 'route' => 'proxy', 'failure_phase' => 'proxy_connect']);
        $result = $this->validator('figma')->check('person@example.com');
        $this->assertSame('Error', $result->status);
        $this->assertSame($this->transport->failure->diagnostics, $result->metadata['transport_error']);
    }

    public function test_browser_headers_and_exact_profile_are_preserved(): void
    {
        $this->transport->responses = [[200, '{"B":{"A?":"B"}}']];
        $this->validator('canva')->check('person@example.com');
        $spec = $this->transport->specs[0];
        $this->assertSame('chrome120', $spec['impersonate']);
        $this->assertArrayNotHasKey('User-Agent', $spec['headers']);
        $this->assertSame('https://www.canva.com/login', $spec['headers']['Referer']);
        $this->assertSame('{"C":"Linux / Chrome","A?":"E","L":"person@example.com"}', $spec['body']);
    }

    public function test_challenge_page_is_unknown_before_followup_requests(): void
    {
        $this->transport->responses = [[403, '<title>Just a moment...</title>']];
        $result = $this->validator('threadless')->check('person@example.com');
        $this->assertSame('Error', $result->status);
        $this->assertSame(403, $result->metadata['http_status']);
        $this->assertCount(1, $this->transport->specs);
    }

    public static function profileCases(): array
    {
        return require __DIR__.'/profile-email-fixtures.php';
    }

    private function validator(string $key): DeclarativeEmailValidator
    {
        return new DeclarativeEmailValidator(collect(config('scanner_september_email.modules'))->firstWhere('key', $key));
    }
}

final class QueuedProfileFfiTransport extends PhpFfiTransport
{
    public array $responses = [];

    public array $specs = [];

    public ?ConnectionException $failure = null;

    public function send(array $spec, CookieJar $jar): Response
    {
        $this->specs[] = $spec;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->responses === []) {
            throw new ConnectionException('Offline native fixture queue is empty');
        }
        [$status, $body, $headers] = array_pad(array_shift($this->responses), 3, []);
        if (is_array($body)) {
            $body = json_encode($body, JSON_THROW_ON_ERROR);
            $headers['Content-Type'] ??= 'application/json';
        }

        return new Response(new PsrResponse($status, $headers, $body));
    }
}
