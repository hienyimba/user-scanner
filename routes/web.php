<?php

use App\Http\Controllers\OpsMetricsController;
use App\Http\Controllers\ApiTesterController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/scanner', [ScanController::class, 'index'])->name('scanner.index');
Route::post('/scanner', [ScanController::class, 'run'])->name('scanner.run');
Route::get('/api-tester', [ApiTesterController::class, 'index'])->name('api-tester.index');
Route::get('/api-tester/external', [ApiTesterController::class, 'external'])->name('api-tester.external');
Route::get('/eda2822d-b607-4cfe-f66z-59a90/metrics', [OpsMetricsController::class, 'index'])->name('ops.metrics');
