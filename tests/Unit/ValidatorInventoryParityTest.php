<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ValidatorInventoryParityTest extends TestCase
{
    public function test_python_and_php_generated_counts_match(): void
    {
        $root = dirname(__DIR__, 2);

        $pythonUser = glob($root . '/user-scanner-py/user_scanner/user_scan/*/*.py') ?: [];
        $pythonEmail = glob($root . '/user-scanner-py/user_scanner/email_scan/*/*.py') ?: [];
        $phpUser = glob($root . '/app/Services/Scanner/Validators/Generated/User/*Validator.php') ?: [];
        $phpEmail = glob($root . '/app/Services/Scanner/Validators/Generated/Email/*Validator.php') ?: [];

        $pythonUser = array_values(array_filter($pythonUser, static fn (string $path): bool => !str_ends_with($path, '__init__.py')));
        $pythonEmail = array_values(array_filter($pythonEmail, static fn (string $path): bool => !str_ends_with($path, '__init__.py')));

        self::assertCount(count($pythonUser), $phpUser, 'Generated user validator count drifted from Python inventory.');
        self::assertCount(count($pythonEmail), $phpEmail, 'Generated email validator count drifted from Python inventory.');
    }

    public function test_generated_config_registers_all_generated_validators(): void
    {
        $root = dirname(__DIR__, 2);
        $config = require $root . '/config/scanner_generated.php';

        $phpUser = glob($root . '/app/Services/Scanner/Validators/Generated/User/*Validator.php') ?: [];
        $phpEmail = glob($root . '/app/Services/Scanner/Validators/Generated/Email/*Validator.php') ?: [];
        $expected = count($phpUser) + count($phpEmail);

        self::assertArrayHasKey('validators', $config);
        self::assertCount($expected, $config['validators'], 'scanner_generated registry does not include every generated validator.');
    }
}
