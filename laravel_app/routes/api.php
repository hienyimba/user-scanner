<?php

use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\ScanController;
use Illuminate\Support\Facades\Route;

Route::post('/scanner/run', [ScanController::class, 'run']);
Route::get('/scanner/modules/{mode}', [ScanController::class, 'modules']);

Route::post('/scanner/runs', [RunController::class, 'create']);
Route::get('/scanner/runs', [RunController::class, 'index']);
Route::get('/scanner/runs/{runId}', [RunController::class, 'show']);
Route::get('/scanner/runs/{runId}/export/{format}', [RunController::class, 'export']);
