<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/scans/new', [ScanController::class, 'create'])->name('scans.create');
Route::post('/scans', [ScanController::class, 'store'])->name('scans.store');
Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('scans.show');
Route::post('/scans/{scan}/cancel', [ScanController::class, 'cancel'])->name('scans.cancel');
Route::get('/scans/{scan}/export/{format}', [ScanController::class, 'export'])->whereIn('format', ['json', 'csv'])->name('scans.export');
