<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovementController;
use Illuminate\Support\Facades\Route;

Route::get('/manifest.webmanifest', fn () => response()->file(public_path('manifest.webmanifest'), ['Content-Type' => 'application/manifest+json']))->name('pwa.manifest');
Route::get('/sw.js', fn () => response()->file(public_path('sw.js'), ['Content-Type' => 'application/javascript', 'Service-Worker-Allowed' => '/']))->name('pwa.worker');

Route::get('/login', [AuthController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware(['guest', 'throttle:5,1'])->name('login.store');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/clientes', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clientes', [ClientController::class, 'store'])->name('clients.store');
    Route::put('/clientes/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::patch('/clientes/{client}/estado', [ClientController::class, 'toggle'])->name('clients.toggle');
    Route::post('/clientes/{client}/cobros', [ClientController::class, 'payment'])->name('clients.payment');
    Route::get('/movimientos', [MovementController::class, 'index'])->name('movements.index');
    Route::get('/movimientos/exportar.csv', [MovementController::class, 'export'])->name('movements.export');
    Route::post('/movimientos', [MovementController::class, 'store'])->name('movements.store');
    Route::delete('/movimientos/{transaction}', [MovementController::class, 'destroy'])->name('movements.destroy');
    Route::get('/finanzas-personales', [MovementController::class, 'personal'])->name('personal.index');
});
