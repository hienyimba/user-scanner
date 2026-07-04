<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class JuneManualValidatorTest extends TestCase
{
    #[DataProvider('validatorCaseProvider')]
    public function test_june_manual_validators_match_expected_mocked_statuses(string $className, string $target, array $responses, string $expected): void
    {
        $sequence = Http::sequence();
        foreach ($responses as $response) {
            if (array_key_exists('json', $response)) {
                $sequence->push(json_encode($response['json'], JSON_THROW_ON_ERROR), $response['status'], [
                    'Content-Type' => 'application/json',
                ]);
                continue;
            }

            $sequence->push($response['body'] ?? '', $response['status']);
        }

        Http::fake(['*' => $sequence]);

        $validator = new $className();
        $result = $validator->check($target);
        $expectedStatus = match ($expected) {
            'Found' => 'Taken',
            'Not Found' => 'Available',
            default => $expected,
        };

        $this->assertSame($expectedStatus, $result->status, $className . ' returned an unexpected status.');
    }

    public function test_email_loud_modules_follow_june_source_of_truth(): void
    {
        $catalog = config('scanner.loud_modules.email', []);

        $this->assertContains('ama', $catalog);
        $this->assertContains('buymeacoffee', $catalog);
        $this->assertContains('luarocks', $catalog);
        $this->assertNotContains('instagram', $catalog);
        $this->assertNotContains('polarsteps', $catalog);
    }

    /**
     * @return array<int, array{0:string,1:string,2:array<int, array{status:int, body?:string, json?:array<mixed>}>,3:string}>
     */
    public static function validatorCaseProvider(): array
    {
        /** @var array<int, array{class:string,target:string,responses:array<int, array{status:int, body?:string, json?:array<mixed>}>,expected:string}> $cases */
        $cases = require dirname(__DIR__) . '/Fixtures/june_manual_validator_cases.php';

        return array_map(
            static fn (array $case): array => [
                $case['class'],
                $case['target'],
                $case['responses'],
                $case['expected'],
            ],
            $cases,
        );
    }
}
