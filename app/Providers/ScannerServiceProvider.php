<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ValidatorContract;
use App\Services\Scanner\MetadataAuditService;
use App\Services\Scanner\MetadataBaselineValidationService;
use App\Services\Scanner\MetadataEnrichmentService;
use App\Services\Scanner\MetadataCapabilityService;
use App\Services\Scanner\PatternExpanderService;
use App\Services\Scanner\ProfileMetadataExtractor;
use App\Services\Scanner\ProxyManagerService;
use App\Services\Scanner\ScannerEngineService;
use Illuminate\Support\ServiceProvider;

final class ScannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProxyManagerService::class);
        $this->app->singleton(PatternExpanderService::class);
        $this->app->singleton(ProfileMetadataExtractor::class);
        $this->app->singleton(MetadataEnrichmentService::class);
        $this->app->singleton(MetadataCapabilityService::class);
        $this->app->singleton(MetadataAuditService::class);
        $this->app->singleton(MetadataBaselineValidationService::class);

        $this->app->singleton('scanner.validators', function ($app): array {
            $validators = [];

            foreach (config('scanner.validators', []) as $class) {
                /** @var ValidatorContract $validator */
                $validator = $app->make($class);
                $validators[$validator->mode() . ':' . $validator->key()] = $validator;
            }

            foreach (config('scanner_generated.validators', []) as $class) {
                /** @var ValidatorContract $validator */
                $validator = $app->make($class);
                $registryKey = $validator->mode() . ':' . $validator->key();

                if (!isset($validators[$registryKey])) {
                    $validators[$registryKey] = $validator;
                }
            }

            return array_values($validators);
        });

        $this->app->singleton(ScannerEngineService::class, function ($app): ScannerEngineService {
            return new ScannerEngineService(
                validators: $app->make('scanner.validators'),
                proxyManager: $app->make(ProxyManagerService::class),
                patternExpander: $app->make(PatternExpanderService::class),
                metadataEnrichment: $app->make(MetadataEnrichmentService::class),
                metadataCapability: $app->make(MetadataCapabilityService::class),
            );
        });
    }
}
