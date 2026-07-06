<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class MetadataBaselineTargetRegistryTest extends TestCase
{
    public function test_metadata_baseline_target_registry_includes_curated_email_targets(): void
    {
        /** @var array<string, array<string, array<int, string>>> $registry */
        $registry = require base_path('config/scanner_metadata_targets.php');

        $this->assertArrayHasKey('username', $registry);
        $this->assertArrayHasKey('email', $registry);
        $this->assertNotEmpty($registry['email']);

        $expectedModules = [
            'adobe',
            'allen',
            'appletv',
            'coursera',
            'duolingo',
            'etsy',
            'eventbrite',
            'github',
            'gravatar',
            'indiatimes',
            'vedantu',
            'vivino',
            'walmart',
            'wix',
        ];

        $allowedTargets = [
            'baseline_email_primary',
            'baseline_email_secondary',
            'baseline_email_tertiary',
        ];

        foreach ($expectedModules as $module) {
            $this->assertArrayHasKey($module, $registry['email']);
            $this->assertNotEmpty($registry['email'][$module]);

            foreach ($registry['email'][$module] as $target) {
                $this->assertContains($target, $allowedTargets);
            }
        }
    }

    public function test_metadata_baseline_target_registry_merges_env_driven_email_module_targets(): void
    {
        $payload = json_encode([
            'github' => ['baseline_email_custom', 'direct@example.com'],
            'gitlab' => 'baseline_email_gitlab',
            'ignored_empty' => ['   '],
        ], JSON_THROW_ON_ERROR);

        putenv('SCANNER_EMAIL_BASELINE_MODULE_TARGETS=' . $payload);
        $_ENV['SCANNER_EMAIL_BASELINE_MODULE_TARGETS'] = $payload;
        $_SERVER['SCANNER_EMAIL_BASELINE_MODULE_TARGETS'] = $payload;

        try {
            /** @var array<string, array<string, array<int, string>>> $registry */
            $registry = require base_path('config/scanner_metadata_targets.php');
        } finally {
            putenv('SCANNER_EMAIL_BASELINE_MODULE_TARGETS');
            unset($_ENV['SCANNER_EMAIL_BASELINE_MODULE_TARGETS'], $_SERVER['SCANNER_EMAIL_BASELINE_MODULE_TARGETS']);
        }

        $this->assertContains('baseline_email_custom', $registry['email']['github']);
        $this->assertContains('direct@example.com', $registry['email']['github']);
        $this->assertSame(['baseline_email_gitlab'], $registry['email']['gitlab']);
        $this->assertArrayNotHasKey('ignored_empty', $registry['email']);
    }

    public function test_metadata_baseline_target_registry_includes_venmo_username_target(): void
    {
        /** @var array<string, array<string, array<int, string>>> $registry */
        $registry = require base_path('config/scanner_metadata_targets.php');

        $this->assertArrayHasKey('venmo', $registry['username']);
        $this->assertContains('eodioko', $registry['username']['venmo']);
    }
}
