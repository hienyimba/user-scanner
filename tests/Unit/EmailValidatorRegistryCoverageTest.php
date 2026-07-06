<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Scanner\ScannerEngineService;
use Tests\TestCase;

final class EmailValidatorRegistryCoverageTest extends TestCase
{
    public function test_requested_email_platforms_are_present_in_the_registry(): void
    {
        $modules = app(ScannerEngineService::class)->listModules('email');
        $keys = array_values(array_unique(array_map(
            static fn (array $module): string => $module['key'],
            $modules,
        )));
        sort($keys);

        $expectedKeys = [
            'adobe',
            'allen',
            'appletv',
            'bible',
            'coursera',
            'doordash',
            'dropbox',
            'duolingo',
            'etsy',
            'eventbrite',
            'foursquare',
            'github',
            'gitlab',
            'gmail',
            'gravatar',
            'indiatimes',
            'libravatar',
            'otter',
            'pandora',
            'plex',
            'ripe',
            'samsclub',
            'smule',
            'typeform',
            'unavatar',
            'vedantu',
            'vivino',
            'vsco',
            'walmart',
            'wanderlog',
            'wix',
        ];

        foreach ($expectedKeys as $expectedKey) {
            $this->assertContains($expectedKey, $keys, sprintf('Expected "%s" to be registered for email scans.', $expectedKey));
        }
    }
}
