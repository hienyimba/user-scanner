<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scanner\ScannerEngineService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Locks in the production skip controls for modules whose upstream endpoints
 * are verifiably dead or hard-blocked on every exit (direct + proxy pool):
 *
 * - academia/liberapay: Cloudflare managed challenge (HTTP 403) everywhere
 * - reddit: network-security block (HTTP 403) everywhere
 * - apexlegends: tracker.gg PerimeterX block; public API requires a key
 * - walmart/samsclub: identity GraphQL deterministically fails every lookup
 *   with SOMETHING_WENT_WRONG (server-side bot gating)
 * - github (email): TLS-fingerprint bot wall on /signup (403) and 406 on
 *   signup_check on every exit; no unauthenticated email-check path remains
 */
final class WorstModuleSkipControlTest extends TestCase
{
    #[DataProvider('skippedUsernameModuleProvider')]
    public function test_username_module_is_auto_skipped_without_network(string $moduleKey): void
    {
        Http::preventStrayRequests();

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'username',
            validatorKey: $moduleKey,
            target: 'hienyimba',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Skipped', $result->status);
        $this->assertNotSame('', $result->reason);
    }

    #[DataProvider('skippedEmailModuleProvider')]
    public function test_email_module_is_auto_skipped_without_network(string $moduleKey): void
    {
        Http::preventStrayRequests();

        $result = app(ScannerEngineService::class)->runPlannedValidator(
            mode: 'email',
            validatorKey: $moduleKey,
            target: 'hienyimba@gmail.com',
            options: ['enrich_metadata' => false],
        );

        $this->assertSame('Skipped', $result->status);
        $this->assertNotSame('', $result->reason);
    }

    public function test_auto_skip_config_lists_worst_modules(): void
    {
        $usernameSkips = config('scanner.auto_skip.username', []);
        foreach (['academia', 'liberapay', 'reddit', 'apexlegends'] as $key) {
            $this->assertArrayHasKey($key, $usernameSkips);
            $this->assertNotSame('', (string) $usernameSkips[$key]);
        }

        $emailSkips = config('scanner.auto_skip.email', []);
        foreach (['walmart', 'samsclub', 'github'] as $key) {
            $this->assertArrayHasKey($key, $emailSkips);
            $this->assertNotSame('', (string) $emailSkips[$key]);
        }
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function skippedUsernameModuleProvider(): array
    {
        return [
            'academia' => ['academia'],
            'liberapay' => ['liberapay'],
            'reddit' => ['reddit'],
            'apexlegends' => ['apexlegends'],
        ];
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function skippedEmailModuleProvider(): array
    {
        return [
            'walmart' => ['walmart'],
            'samsclub' => ['samsclub'],
            'github' => ['github'],
        ];
    }
}
