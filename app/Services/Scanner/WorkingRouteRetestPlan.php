<?php

declare(strict_types=1);

namespace App\Services\Scanner;

/** Builds diagnostic follow-ups; never changes production routes or skips. */
final class WorkingRouteRetestPlan
{
    private const VERDICTS = [
        'Taken' => true, 'Found' => true, 'Registered' => true,
        'Available' => false, 'Not Found' => false, 'Not Registered' => false,
    ];

    /** @return array<int, array{mode:string,module:string,route:string,aliases:array}> */
    public static function build(array $records): array
    {
        $modules = [];
        foreach ($records as $record) {
            if (! in_array($record['route'] ?? '', ['direct', 'US'], true)
                || ! in_array($record['mode'] ?? '', ['username', 'email'], true)) {
                continue;
            }
            $modules[$record['mode'].':'.$record['module']][] = $record;
        }
        $plan = [];
        foreach ($modules as $rows) {
            $byAlias = [];
            $goodRoutes = [];
            $conflict = false;
            foreach ($rows as $row) {
                $byAlias[$row['alias']][$row['route']] = $row;
                if (array_key_exists($row['status'], self::VERDICTS)) {
                    $goodRoutes[$row['route']] = true;
                }
            }
            foreach ($byAlias as $pair) {
                if (isset($pair['direct'], $pair['US'])
                    && array_key_exists($pair['direct']['status'], self::VERDICTS)
                    && array_key_exists($pair['US']['status'], self::VERDICTS)
                    && self::VERDICTS[$pair['direct']['status']] !== self::VERDICTS[$pair['US']['status']]) {
                    $conflict = true;
                }
            }
            if ($conflict) {
                continue;
            }
            $followups = [];
            if (count($goodRoutes) === 1) {
                $route = array_key_first($goodRoutes);
                $other = $route === 'direct' ? 'US' : 'direct';
                if (array_filter($rows, static fn (array $row): bool => $row['route'] === $other && $row['status'] === 'Error')) {
                    // Include aliases previously stopped on this working route.
                    $followups[$route] = array_keys($byAlias);
                }
            } else {
                foreach ($byAlias as $alias => $pair) {
                    foreach (['direct', 'US'] as $route) {
                        $other = $route === 'direct' ? 'US' : 'direct';
                        if (isset($pair[$route], $pair[$other])
                            && array_key_exists($pair[$route]['status'], self::VERDICTS)
                            && $pair[$other]['status'] === 'Error') {
                            $followups[$route][] = $alias;
                        }
                    }
                }
            }
            foreach ($followups as $route => $aliases) {
                $plan[] = ['mode' => $rows[0]['mode'], 'module' => $rows[0]['module'],
                    'route' => $route, 'aliases' => array_values(array_unique($aliases))];
            }
        }
        usort($plan, static fn (array $a, array $b): int => [$a['mode'], $a['module'], $a['route']] <=> [$b['mode'], $b['module'], $b['route']]);

        return $plan;
    }
}
