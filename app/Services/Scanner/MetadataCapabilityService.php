<?php

declare(strict_types=1);

namespace App\Services\Scanner;

final class MetadataCapabilityService
{
    /** @var array<string, array{mode:string,platform:string,category:string,path:string,level:int,strategy:string,notes:string,validated_level:int|null,validated_at:string|null,validated_targets:array<int,string>,validated_notes:string|null}>|null */
    private ?array $inventory = null;

    /**
     * @return array<int, array{mode:string,platform:string,category:string,path:string,level:int,strategy:string,notes:string,validated_level:int|null,validated_at:string|null,validated_targets:array<int,string>,validated_notes:string|null}>
     */
    public function all(): array
    {
        return array_values($this->loadInventory());
    }

    /**
     * @return array{mode:string,platform:string,category:string,path:string,level:int,strategy:string,notes:string,validated_level:int|null,validated_at:string|null,validated_targets:array<int,string>,validated_notes:string|null}|null
     */
    public function forModule(string $mode, string $platform): ?array
    {
        return $this->loadInventory()[$mode . ':' . $platform] ?? null;
    }

    /**
     * @return array{documented_modules:int,level_3_plus:int,level_4:int,levels:array<string,int>,validated_modules:int,validated_level_3_plus:int,validated_level_4:int,validated_levels:array<string,int>}
     */
    public function summary(): array
    {
        $modeSummary = $this->modeSummary();
        $levels = [
            'level_0' => 0,
            'level_1' => 0,
            'level_2' => 0,
            'level_3' => 0,
            'level_4' => 0,
        ];
        $validatedLevels = [
            'level_0' => 0,
            'level_1' => 0,
            'level_2' => 0,
            'level_3' => 0,
            'level_4' => 0,
        ];

        $level3Plus = 0;
        $level4 = 0;
        $validatedModules = 0;
        $validatedLevel3Plus = 0;
        $validatedLevel4 = 0;
        foreach ($this->loadInventory() as $record) {
            $levels['level_' . $record['level']]++;
            if ($record['validated_level'] !== null) {
                $validatedLevel = (int) $record['validated_level'];
                $validatedLevels['level_' . $validatedLevel]++;
            }
        }

        foreach ($modeSummary as $summary) {
            $level3Plus += $summary['level_3_plus'];
            $level4 += $summary['level_4'];
            $validatedModules += $summary['validated_modules'];
            $validatedLevel3Plus += $summary['validated_level_3_plus'];
            $validatedLevel4 += $summary['validated_level_4'];
        }

        return [
            'documented_modules' => count($this->loadInventory()),
            'level_3_plus' => $level3Plus,
            'level_4' => $level4,
            'levels' => $levels,
            'validated_modules' => $validatedModules,
            'validated_level_3_plus' => $validatedLevel3Plus,
            'validated_level_4' => $validatedLevel4,
            'validated_levels' => $validatedLevels,
        ];
    }

    /**
     * @return array<string, array{documented_modules:int,level_3_plus:int,level_4:int,validated_modules:int,validated_level_3_plus:int,validated_level_4:int}>
     */
    public function modeSummary(): array
    {
        $summary = [
            'username' => [
                'documented_modules' => 0,
                'level_3_plus' => 0,
                'level_4' => 0,
                'validated_modules' => 0,
                'validated_level_3_plus' => 0,
                'validated_level_4' => 0,
            ],
            'email' => [
                'documented_modules' => 0,
                'level_3_plus' => 0,
                'level_4' => 0,
                'validated_modules' => 0,
                'validated_level_3_plus' => 0,
                'validated_level_4' => 0,
            ],
        ];

        foreach ($this->loadInventory() as $record) {
            $mode = (string) ($record['mode'] ?? '');
            if (!isset($summary[$mode])) {
                continue;
            }

            $summary[$mode]['documented_modules']++;
            if ((int) ($record['level'] ?? 0) >= 3) {
                $summary[$mode]['level_3_plus']++;
            }
            if ((int) ($record['level'] ?? 0) >= 4) {
                $summary[$mode]['level_4']++;
            }
            if (($record['validated_level'] ?? null) !== null) {
                $summary[$mode]['validated_modules']++;
                $validatedLevel = (int) $record['validated_level'];
                if ($validatedLevel >= 3) {
                    $summary[$mode]['validated_level_3_plus']++;
                }
                if ($validatedLevel >= 4) {
                    $summary[$mode]['validated_level_4']++;
                }
            }
        }

        return $summary;
    }

    /**
     * @return array{
     *   mode:string,
     *   promotion_candidates:int,
     *   promotion_candidate_platforms:array<int, string>,
     *   promotion_candidates_with_baseline_targets:int,
     *   promotion_candidate_platforms_with_baseline_targets:array<int, string>,
     *   promotion_candidates_without_baseline_targets:int,
     *   promotion_candidate_platforms_without_baseline_targets:array<int, string>,
     *   safety_blocked_modules:int,
     *   safety_blocked_platforms:array<int, string>,
     *   validated_below_documented_modules:int,
     *   validated_below_documented_platforms:array<int, string>
     * }
     */
    public function validationGapSummary(string $mode): array
    {
        $baselineRegistry = $this->baselineRegistry()[$mode] ?? [];
        $promotionCandidatePlatforms = [];
        $promotionCandidatePlatformsWithBaselines = [];
        $promotionCandidatePlatformsWithoutBaselines = [];
        $safetyBlockedPlatforms = [];
        $validatedBelowDocumentedPlatforms = [];

        foreach ($this->loadInventory() as $record) {
            if (($record['mode'] ?? null) !== $mode) {
                continue;
            }

            $platform = (string) $record['platform'];
            $level = (int) $record['level'];
            $validatedLevel = is_numeric($record['validated_level'] ?? null) ? (int) $record['validated_level'] : null;
            $strategy = (string) ($record['strategy'] ?? '');
            $hasBaselineTargets = is_array($baselineRegistry[$platform] ?? null) && ($baselineRegistry[$platform] ?? []) !== [];

            if ($strategy === 'safety-blocked-placeholder') {
                $safetyBlockedPlatforms[] = $platform;
            }

            if ($validatedLevel !== null && $validatedLevel < $level) {
                $validatedBelowDocumentedPlatforms[] = $platform;
            }

            if ($level < 3 || $validatedLevel !== null || $strategy === 'safety-blocked-placeholder') {
                continue;
            }

            $promotionCandidatePlatforms[] = $platform;
            if ($hasBaselineTargets) {
                $promotionCandidatePlatformsWithBaselines[] = $platform;
            } else {
                $promotionCandidatePlatformsWithoutBaselines[] = $platform;
            }
        }

        sort($promotionCandidatePlatforms);
        sort($promotionCandidatePlatformsWithBaselines);
        sort($promotionCandidatePlatformsWithoutBaselines);
        sort($safetyBlockedPlatforms);
        sort($validatedBelowDocumentedPlatforms);

        return [
            'mode' => $mode,
            'promotion_candidates' => count($promotionCandidatePlatforms),
            'promotion_candidate_platforms' => $promotionCandidatePlatforms,
            'promotion_candidates_with_baseline_targets' => count($promotionCandidatePlatformsWithBaselines),
            'promotion_candidate_platforms_with_baseline_targets' => $promotionCandidatePlatformsWithBaselines,
            'promotion_candidates_without_baseline_targets' => count($promotionCandidatePlatformsWithoutBaselines),
            'promotion_candidate_platforms_without_baseline_targets' => $promotionCandidatePlatformsWithoutBaselines,
            'safety_blocked_modules' => count($safetyBlockedPlatforms),
            'safety_blocked_platforms' => $safetyBlockedPlatforms,
            'validated_below_documented_modules' => count($validatedBelowDocumentedPlatforms),
            'validated_below_documented_platforms' => $validatedBelowDocumentedPlatforms,
        ];
    }

    /**
     * @return array<string, array{mode:string,platform:string,category:string,path:string,level:int,strategy:string,notes:string,validated_level:int|null,validated_at:string|null,validated_targets:array<int,string>,validated_notes:string|null}>
     */
    private function loadInventory(): array
    {
        if ($this->inventory !== null) {
            return $this->inventory;
        }

        $inventory = [];
        $validationBaselines = $this->validationBaselines();
        $root = base_path('user-scanner-py-june-release/user_scanner');
        foreach (['user_scan' => 'username', 'email_scan' => 'email'] as $directory => $mode) {
            $base = $root . DIRECTORY_SEPARATOR . $directory;
            if (!is_dir($base)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'py' || $file->getFilename() === '__init__.py') {
                    continue;
                }

                $path = $file->getPathname();
                if (str_contains(str_replace('\\', '/', $path), '/abandoned/')) {
                    continue;
                }

                $source = file_get_contents($path);
                if ($source === false) {
                    continue;
                }

                $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), '\\/'));
                $platform = pathinfo($path, PATHINFO_FILENAME);
                $category = basename(dirname($path));

                [$level, $strategy, $notes] = $this->classify($source, $mode);
                $validation = $validationBaselines[$mode][$platform] ?? null;

                $inventory[$mode . ':' . $platform] = [
                    'mode' => $mode,
                    'platform' => $platform,
                    'category' => $category,
                    'path' => $relative,
                    'level' => $level,
                    'strategy' => $strategy,
                    'notes' => $notes,
                    'validated_level' => is_array($validation) && is_numeric($validation['validated_level'] ?? null) ? (int) $validation['validated_level'] : null,
                    'validated_at' => is_array($validation) && is_string($validation['validated_at'] ?? null) ? $validation['validated_at'] : null,
                    'validated_targets' => is_array($validation['targets'] ?? null) ? array_values(array_map('strval', $validation['targets'])) : [],
                    'validated_notes' => is_array($validation) && is_string($validation['notes'] ?? null) ? $validation['notes'] : null,
                ];
            }
        }

        foreach ($this->capabilityOverrides() as $mode => $records) {
            if (!is_array($records)) {
                continue;
            }

            foreach ($records as $platform => $override) {
                if (!is_array($override)) {
                    continue;
                }

                $key = $mode . ':' . $platform;
                $existing = $inventory[$key] ?? null;
                $validation = $validationBaselines[$mode][$platform] ?? null;

                $inventory[$key] = [
                    'mode' => (string) ($override['mode'] ?? $mode),
                    'platform' => (string) ($override['platform'] ?? $platform),
                    'category' => (string) ($override['category'] ?? ($existing['category'] ?? 'other')),
                    'path' => (string) ($override['path'] ?? ($existing['path'] ?? 'private-laravel')),
                    'level' => is_numeric($override['level'] ?? null) ? (int) $override['level'] : (int) ($existing['level'] ?? 1),
                    'strategy' => (string) ($override['strategy'] ?? ($existing['strategy'] ?? 'unknown')),
                    'notes' => (string) ($override['notes'] ?? ($existing['notes'] ?? 'No capability audit entry found')),
                    'validated_level' => is_array($validation) && is_numeric($validation['validated_level'] ?? null)
                        ? (int) $validation['validated_level']
                        : ($existing['validated_level'] ?? null),
                    'validated_at' => is_array($validation) && is_string($validation['validated_at'] ?? null)
                        ? $validation['validated_at']
                        : ($existing['validated_at'] ?? null),
                    'validated_targets' => is_array($validation['targets'] ?? null)
                        ? array_values(array_map('strval', $validation['targets']))
                        : ($existing['validated_targets'] ?? []),
                    'validated_notes' => is_array($validation) && is_string($validation['notes'] ?? null)
                        ? $validation['notes']
                        : ($existing['validated_notes'] ?? null),
                ];
            }
        }

        ksort($inventory);
        $this->inventory = $inventory;

        return $inventory;
    }

    /**
     * @return array<string, mixed>
     */
    private function validationBaselines(): array
    {
        $path = base_path('config/scanner_metadata_validations.php');
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilityOverrides(): array
    {
        $path = base_path('config/scanner_metadata_capability_overrides.php');
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function baselineRegistry(): array
    {
        $path = base_path('config/scanner_metadata_targets.php');
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * @return array{0:int,1:string,2:string}
     */
    private function classify(string $source, string $mode): array
    {
        $hasExtra = preg_match('/Result\.(?:taken|available)\([^)]*extra\s*=/s', $source) === 1;
        $hasProfileReference = preg_match('/f["\']https?:\/\/.*\{user\}|https?:\/\/[^"\']*\{user\}|https?:\/\/[^"\']*\{username\}/i', $source) === 1;
        $hasSiteEvidenceReference = $hasProfileReference || preg_match('/show_url\s*=/i', $source) === 1;
        $keys = $this->extractMetadataKeys($source);
        $richSignals = array_intersect($keys, [
            'name',
            'full_name',
            'real_name',
            'display_name',
            'bio',
            'location',
            'website',
            'website_url',
            'email',
            'public_email',
            'followers',
            'following',
            'avatar',
            'avatar_url',
            'links',
            'external_links',
            'created_at',
            'joined',
            'registration',
        ]);

        if ($mode === 'username') {
            if ($hasProfileReference) {
                return [4, 'profile-html-enrichment', 'Public profile URL plus generic HTML/JSON-LD enrichment, evidence, and confidence'];
            }
            if ($hasExtra) {
                return [3, 'direct-extra-normalization', 'Structured metadata is extracted directly by the module and normalized through the enrichment layer'];
            }

            return [2, 'positive-match-only', 'Successful matches are supported, but no public profile URL or structured metadata signals were detected'];
        }

        if ($hasExtra && (count($keys) >= 2 || count($richSignals) >= 1)) {
            return [4, 'account-evidence-enrichment', 'Positive account checks return structured metadata and confidence through the enrichment layer'];
        }
        if ($hasExtra) {
            return [3, 'direct-extra-normalization', 'Structured metadata is extracted directly by the module and normalized through the enrichment layer'];
        }
        if ($hasSiteEvidenceReference) {
            return [2, 'account-url-only', 'The module exposes account evidence or a public site URL but no structured metadata extraction was detected'];
        }

        return [1, 'found-not-found-only', 'Only registration detection was detected'];
    }

    /**
     * @return array<int, string>
     */
    private function extractMetadataKeys(string $source): array
    {
        $keys = [];
        if (preg_match_all('/extra\s*=\s*\{([^}]*)\}/s', $source, $blocks) !== false) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/["\']([^"\']+)["\']\s*:/', $block, $matches) !== false) {
                    foreach ($matches[1] as $key) {
                        $keys[] = strtolower(str_replace(' ', '_', trim($key)));
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
