<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Scanner\MetadataCapabilityService;
use App\Services\Scanner\ScannerEngineService;

final class ModuleCatalogPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function apiPayload(
        string $mode,
        ScannerEngineService $engine,
        MetadataCapabilityService $metadataCapability,
        bool $noNsfw = false,
    ): array {
        return [
            'ok' => true,
            'mode' => $mode,
            'metadata_summary' => $metadataCapability->summary(),
            'categories' => $engine->listCategories($mode, $noNsfw),
            'modules' => $engine->listModules($mode, $noNsfw),
        ];
    }
}
