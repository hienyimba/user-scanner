<?php

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/scanner', [ScanController::class, 'index'])->name('scanner.index');
Route::post('/scanner', [ScanController::class, 'run'])->name('scanner.run');
