<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\Contracts\ValidatorContract;
use App\DTO\ScanResult;
use Illuminate\Support\Arr;

final class ScannerEngineService
{
    /** @param iterable<ValidatorContract> $validators */
    public function __construct(
        private readonly iterable $validators,
        private readonly ProxyManagerService $proxyManager
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, ScanResult>
     */
    public function scan(string $target, string $mode, ?string $category = null, ?array $moduleKeys = null, array $options = []): array
    {
        $selected = [];

        foreach ($this->validators as $validator) {
            if ($validator->mode() !== $mode) {
                continue;
            }

            if ($category !== null && strcasecmp($validator->category(), $category) !== 0) {
                continue;
            }

            if ($moduleKeys !== null && !in_array($validator->key(), $moduleKeys, true)) {
                continue;
            }

            $selected[] = $validator;
        }

        usort($selected, static fn (ValidatorContract $a, ValidatorContract $b): int => [$a->category(), $a->siteName()] <=> [$b->category(), $b->siteName()]);

        $results = [];
        foreach ($selected as $validator) {
            $proxy = Arr::get($options, 'use_proxy') ? $this->proxyManager->next() : null;
            $results[] = $validator->check($target, [
                ...$options,
                'proxy' => $proxy,
            ]);
        }

        return $results;
    }

    /**
     * @return array<int, array{key:string,category:string,mode:string,site_name:string,url:string}>
     */
    public function listModules(string $mode): array
    {
        $modules = [];
        foreach ($this->validators as $validator) {
            if ($validator->mode() !== $mode) {
                continue;
            }

            $modules[] = [
                'key' => $validator->key(),
                'category' => $validator->category(),
                'mode' => $validator->mode(),
                'site_name' => $validator->siteName(),
                'url' => $validator->siteUrl(),
            ];
        }

        usort($modules, static fn (array $a, array $b): int => [$a['category'], $a['site_name']] <=> [$b['category'], $b['site_name']]);

        return $modules;
    }
}
