<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AccountReverseCheck;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AccountReverseCheckTest extends TestCase
{
    public function test_it_uses_scanner_api_and_normalizes_results_to_legacy_shape(): void
    {
        Http::fake([
            'https://scanner.test/api/v1/scan' => Http::response([
                'ok' => true,
                'run_id' => 'run-123',
                'status' => 'queued',
                'mode' => 'username',
                'target' => 'alice',
            ], 202),
            'https://scanner.test/api/v1/scan/run-123/final' => Http::sequence()
                ->push([
                    'ok' => true,
                    'ready' => false,
                    'run' => [
                        'id' => 'run-123',
                        'status' => 'running',
                    ],
                ], 202)
                ->push([
                    'ok' => true,
                    'ready' => true,
                    'run' => [
                        'id' => 'run-123',
                        'status' => 'completed',
                    ],
                    'results' => [[
                        'site_name' => 'Alpha',
                        'category' => 'social',
                        'url' => 'https://alpha.test',
                        'profile_url' => 'https://alpha.test/users/alice',
                        'status' => 'Found',
                        'normalized_status' => 'found',
                        'extra' => "Name: Alice Example\nFollowers: 42",
                        'metadata' => [
                            'display_name' => 'Alice Example',
                            'followers' => 42,
                            'external_links' => ['https://portfolio.test'],
                            'status_detail' => 'found',
                            'observed_metadata_level' => 4,
                            'evidence' => ['profile_url', 'display_name'],
                        ],
                    ]],
                ], 200),
        ]);

        $service = $this->makeService(
            scannerApiBaseUrl: 'https://scanner.test/api',
            pollIntervalMs: 100,
            maxPollAttempts: 3,
        );

        $result = $service->fetch('alice', 'reverse_username');

        $this->assertSame('reverse_osint_check_v1', $result['source_id']);
        $this->assertSame('alice', $result['query']);
        $this->assertSame('username', $result['raw']['type']);
        $this->assertSame(1, $result['raw']['count']);
        $this->assertSame('completed', $result['raw']['run']['status']);

        $row = $result['raw']['results'][0];
        $this->assertSame('Alpha', $row['platform']);
        $this->assertSame('social', $row['category']);
        $this->assertSame('https://alpha.test/users/alice', $row['url']);
        $this->assertTrue($row['exists']);
        $this->assertSame('alpha.test', $row['domain']);
        $this->assertSame('https://www.google.com/s2/favicons?domain=alpha.test&sz=128', $row['icon_url']);
        $this->assertSame('Display Name', $row['metadata'][0]['label']);
        $this->assertSame('Alice Example', $row['metadata'][0]['value']);
        $this->assertSame('Followers', $row['metadata'][1]['label']);
        $this->assertSame('42', $row['metadata'][1]['value']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://scanner.test/api/v1/scan') {
                return true;
            }

            return $request['mode'] === 'username'
                && $request['target'] === 'alice'
                && $request['show_hits'] === true
                && $request['use_proxy'] === false;
        });
    }

    public function test_it_returns_empty_legacy_payload_for_phone_queries(): void
    {
        Http::fake();

        $service = $this->makeService(scannerApiBaseUrl: 'https://scanner.test/api');
        $result = $service->fetch('+15551234567', 'reverse_phone');

        $this->assertSame('phone', $result['raw']['type']);
        $this->assertSame(0, $result['raw']['count']);
        $this->assertSame([], $result['raw']['results']);
        $this->assertSame('Phone lookups are not supported by the scanner API.', $result['raw']['error']);

        Http::assertNothingSent();
    }

    public function test_it_prepends_connected_email_for_email_queries(): void
    {
        Http::fake([
            'https://scanner.test/api/v1/scan' => Http::response([
                'ok' => true,
                'run_id' => 'run-456',
            ], 202),
            'https://scanner.test/api/v1/scan/run-456/final' => Http::response([
                'ok' => true,
                'ready' => true,
                'run' => [
                    'id' => 'run-456',
                    'status' => 'completed',
                ],
                'results' => [[
                    'site_name' => 'Mailbox',
                    'category' => 'social',
                    'url' => 'https://mailbox.test',
                    'status' => 'Registered',
                    'normalized_status' => 'found',
                    'metadata' => [
                        'display_name' => 'Alice Example',
                    ],
                ]],
            ], 200),
        ]);

        $service = $this->makeService(scannerApiBaseUrl: 'https://scanner.test/api');
        $result = $service->fetch('alice@example.com', 'reverse_email');

        $this->assertSame('Connected Email', $result['raw']['results'][0]['metadata'][0]['label']);
        $this->assertSame('alice@example.com', $result['raw']['results'][0]['metadata'][0]['value']);
        $this->assertSame('email', $result['raw']['results'][0]['metadata'][0]['kind']);
    }

    private function makeService(
        string $scannerApiBaseUrl,
        int $pollIntervalMs = 1500,
        int $maxPollAttempts = 40,
    ): AccountReverseCheck {
        return new class($scannerApiBaseUrl, $pollIntervalMs, $maxPollAttempts) extends AccountReverseCheck {
            public function __construct(string $scannerApiBaseUrl, int $pollIntervalMs, int $maxPollAttempts)
            {
                $this->scannerApiBaseUrl = $scannerApiBaseUrl;
                $this->pollIntervalMs = $pollIntervalMs;
                $this->maxPollAttempts = $maxPollAttempts;

                parent::__construct();
            }
        };
    }
}
