<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ValidatorContract;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\ScannerEngineService;
use Illuminate\Support\ServiceProvider;

final class ScannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProxyManagerService::class);

        $this->app->singleton('scanner.validators', function ($app): array {
            $manual = config('scanner.validators', []);
            $generated = config('scanner_generated.validators', []);
            $classes = array_values(array_unique(array_merge($manual, $generated)));

            return array_map(static fn (string $class): ValidatorContract => $app->make($class), $classes);
        });

        $this->app->singleton(ScannerEngineService::class, function ($app): ScannerEngineService {
            return new ScannerEngineService(
                validators: $app->make('scanner.validators'),
                proxyManager: $app->make(ProxyManagerService::class)
            );
        });
    }
}
