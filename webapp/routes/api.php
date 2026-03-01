<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/scans', [ScanController::class, 'store'])->name('api.scans.store');
    Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('api.scans.show');
    Route::post('/scans/{scan}/cancel', [ScanController::class, 'cancel'])->name('api.scans.cancel');
    Route::get('/scans/{scan}/export/{format}', [ScanController::class, 'export'])->whereIn('format', ['json', 'csv'])->name('api.scans.export');
});
