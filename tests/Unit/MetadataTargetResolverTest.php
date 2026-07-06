<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Scanner\MetadataTargetResolver;
use Tests\TestCase;

final class MetadataTargetResolverTest extends TestCase
{
    public function test_resolve_many_uses_email_aliases_from_config(): void
    {
        config()->set('scanner_private_targets.email', [
            'baseline_email_primary' => 'first@example.com',
            'baseline_email_secondary' => 'second@example.com',
        ]);

        $resolver = app(MetadataTargetResolver::class);
        $resolved = $resolver->resolveMany('email', [
            'baseline_email_primary',
            'baseline_email_secondary',
            'missing_alias',
            'Direct@Example.com',
        ]);

        $this->assertSame([
            'first@example.com',
            'second@example.com',
            'direct@example.com',
        ], $resolved['resolved']);
        $this->assertSame(['missing_alias'], $resolved['unresolved']);
    }

    public function test_resolve_many_leaves_username_targets_unchanged(): void
    {
        $resolver = app(MetadataTargetResolver::class);
        $resolved = $resolver->resolveMany('username', ['torvalds', 'sindresorhus']);

        $this->assertSame(['torvalds', 'sindresorhus'], $resolved['resolved']);
        $this->assertSame([], $resolved['unresolved']);
    }
}
