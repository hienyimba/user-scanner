<?php

use App\Http\Controllers\Api\PublicScanController;
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('/v1/scan')->group(function (): void {
    Route::post('/', [PublicScanController::class, 'create']);
    Route::get('/{runId}', [PublicScanController::class, 'show']);
    Route::get('/modules/{mode}', [PublicScanController::class, 'modules']);
    Route::options('/', fn () => response('', 204));
    Route::options('/{runId}', fn () => response('', 204));
    Route::options('/modules/{mode}', fn () => response('', 204));
})->middleware('public.api.cors');

Route::post('/scanner/run', [ScanController::class, 'run']);
Route::get('/scanner/modules/{mode}', [ScanController::class, 'modules']);
Route::get('/scanner/categories/{mode}', [ScanController::class, 'modules']);

Route::post('/scanner/runs', [RunController::class, 'create']);
Route::get('/scanner/runs', [RunController::class, 'index']);
Route::get('/scanner/runs/{runId}', [RunController::class, 'show']);
Route::get('/scanner/runs/{runId}/export/{format}', [RunController::class, 'export']);
