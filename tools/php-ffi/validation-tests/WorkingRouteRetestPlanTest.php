<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Scanner\WorkingRouteRetestPlan;
use PHPUnit\Framework\TestCase;

final class WorkingRouteRetestPlanTest extends TestCase
{
    private function row(string $module, string $route, string $status, string $alias = 'primary'): array
    {
        return ['mode' => 'email', 'module' => $module, 'route' => $route, 'status' => $status, 'alias' => $alias];
    }

    public function test_followups_include_stopped_aliases_on_the_only_working_route(): void
    {
        $plan = WorkingRouteRetestPlan::build([
            $this->row('sample', 'direct', 'Error'), $this->row('sample', 'US', 'Not Registered'),
            $this->row('sample', 'direct', 'Not tested', 'secondary'), $this->row('sample', 'US', 'Not tested', 'secondary'),
        ]);
        $this->assertSame([['mode' => 'email', 'module' => 'sample', 'route' => 'US', 'aliases' => ['primary', 'secondary']]], $plan);
    }

    public function test_conflicting_verdicts_exclude_the_entire_module(): void
    {
        $this->assertSame([], WorkingRouteRetestPlan::build([
            $this->row('sample', 'direct', 'Registered'), $this->row('sample', 'US', 'Not Registered'),
            $this->row('sample', 'direct', 'Error', 'secondary'), $this->row('sample', 'US', 'Registered', 'secondary'),
        ]));
    }

    public function test_case_specific_error_uses_the_other_route_when_both_work_for_other_aliases(): void
    {
        $plan = WorkingRouteRetestPlan::build([
            $this->row('sample', 'direct', 'Error'), $this->row('sample', 'US', 'Registered'),
            $this->row('sample', 'direct', 'Not Registered', 'secondary'), $this->row('sample', 'US', 'Not Registered', 'secondary'),
        ]);
        $this->assertSame(['primary'], $plan[0]['aliases']);
        $this->assertSame('US', $plan[0]['route']);
    }

    public function test_transport_errors_or_unperformed_checks_never_count_as_working_routes(): void
    {
        $this->assertSame([], WorkingRouteRetestPlan::build([
            $this->row('sample', 'direct', 'Error'), $this->row('sample', 'US', 'Not tested'),
        ]));
    }
}
