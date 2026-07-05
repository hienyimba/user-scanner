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
            'coursera',
            'etsy',
            'indiatimes',
            'vedantu',
            'vivino',
            'walmart',
            'wix',
        ];

        $allowedTargets = [
            'hienyimba@gmail.com',
            'kaifcodec@gmail.com',
            'andrew.brumbelow@gmail.com',
        ];

        foreach ($expectedModules as $module) {
            $this->assertArrayHasKey($module, $registry['email']);
            $this->assertNotEmpty($registry['email'][$module]);

            foreach ($registry['email'][$module] as $target) {
                $this->assertContains($target, $allowedTargets);
            }
        }
    }
}
