<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\Validators\Generated\Manual\Email\DropboxEmailValidator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DropboxEmailValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    #[DataProvider('verdictProvider')]
    public function test_dropbox_two_step_flow_returns_upstream_verdicts(array $payload, string $status): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('<html>Dropbox</html>', 200, ['Set-Cookie' => 't=csrf-token-1; Path=/; Secure'])
            ->push($payload, 200)]);

        $result = (new DropboxEmailValidator)->check('person@example.com');

        $this->assertSame($status, $result->status);
        $this->assertSame($payload['exists_as_recovery_email'], $result->metadata['exists_as_recovery_email']);
        $this->assertSame($payload['has_passkey'], $result->metadata['has_passkey']);
        $this->assertSame($payload['exists'] || $payload['exists_as_recovery_email'], $result->metadata['account_exists']);
        $this->assertSame(['has_passkey'], $result->metadata['sensitive_fields']);
        $this->assertSame([
            'https://www.dropbox.com',
            'https://www.dropbox.com/2/account/check_user_with_email_exists',
        ], Http::recorded()->map(static fn (array $exchange): string => $exchange[0]->url())->all());
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://www.dropbox.com/2/account/check_user_with_email_exists'
                && $request->hasHeader('X-CSRF-Token', 'csrf-token-1')
                && $request->method() === 'POST'
                && $request->data() === ['email' => 'person@example.com'];
        });
    }

    #[DataProvider('invalidPayloadProvider')]
    public function test_dropbox_rejects_changed_response_shape(array $payload): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('<html>Dropbox</html>', 200, ['Set-Cookie' => 't=csrf-token-1; Path=/'])
            ->push($payload, 200)]);

        $result = (new DropboxEmailValidator)->check('person@example.com');

        $this->assertSame('Error', $result->status);
        $this->assertSame('Dropbox response structure changed', $result->reason);
        Http::assertSentCount(2);
        $this->assertArrayNotHasKey('has_passkey', $result->metadata);
    }

    public function test_dropbox_requires_csrf_cookie(): void
    {
        Http::fake(['*' => Http::response('<html>Dropbox</html>', 200)]);

        $result = (new DropboxEmailValidator)->check('person@example.com');

        $this->assertSame('Error', $result->status);
        $this->assertSame('Dropbox CSRF token not retrieved', $result->reason);
        Http::assertSentCount(1);
    }

    public function test_dropbox_rate_limit_stops_before_the_account_check(): void
    {
        Http::fake(['*' => Http::response('Too many requests', 429, ['Retry-After' => '300'])]);
        $result = (new DropboxEmailValidator)->check('person@example.com');
        $this->assertSame('Error', $result->status);
        $this->assertSame(429, $result->metadata['http_status']);
        $this->assertSame(300, $result->metadata['retry_after_seconds']);
        Http::assertSentCount(1);
    }

    public static function invalidPayloadProvider(): array
    {
        return [
            'missing evidence' => [['exists' => true]],
            'string exists' => [['exists' => 'false', 'exists_as_recovery_email' => false, 'has_passkey' => false]],
            'integer recovery flag' => [['exists' => false, 'exists_as_recovery_email' => 0, 'has_passkey' => false]],
            'string passkey flag' => [['exists' => false, 'exists_as_recovery_email' => false, 'has_passkey' => 'false']],
        ];
    }

    /** @return array<string, array{0:array<string,bool>,1:string}> */
    public static function verdictProvider(): array
    {
        return [
            'primary email' => [['exists' => true, 'exists_as_recovery_email' => false, 'has_passkey' => true], 'Registered'],
            'recovery email' => [['exists' => false, 'exists_as_recovery_email' => true, 'has_passkey' => false], 'Registered'],
            'not associated' => [['exists' => false, 'exists_as_recovery_email' => false, 'has_passkey' => false], 'Not Registered'],
        ];
    }
}
