<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientInstallationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedExpenseController;
use App\Http\Controllers\InfrastructureController;
use App\Http\Controllers\InstallationDatabaseController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\PersonalExpenseController;
use App\Http\Controllers\PersonalRecurringExpenseController;
use App\Http\Middleware\EnsureOwner;
use Illuminate\Support\Facades\Route;

Route::get('/manifest.webmanifest', fn () => response()->file(public_path('manifest.webmanifest'), ['Content-Type' => 'application/manifest+json']))->name('pwa.manifest');
Route::get('/sw.js', fn () => response()->file(public_path('sw.js'), ['Content-Type' => 'application/javascript', 'Service-Worker-Allowed' => '/']))->name('pwa.worker');

Route::get('/login', [AuthController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware(['guest', 'throttle:5,1'])->name('login.store');
Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', EnsureOwner::class])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/clientes', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clientes/{client}/instalaciones', [ClientInstallationController::class, 'index'])->name('clients.installations.index');
    Route::post('/clientes/{client}/instalaciones', [ClientInstallationController::class, 'store'])->name('clients.installations.store');
    Route::put('/clientes/{client}/instalaciones/{installation}', [ClientInstallationController::class, 'update'])->name('clients.installations.update');
    Route::delete('/clientes/{client}/instalaciones/{installation}', [ClientInstallationController::class, 'destroy'])->name('clients.installations.destroy');
    Route::post('/clientes/{client}/instalaciones/{installation}/bases', [InstallationDatabaseController::class, 'store'])->name('clients.installations.databases.store');
    Route::put('/clientes/{client}/instalaciones/{installation}/bases/{database}', [InstallationDatabaseController::class, 'update'])->name('clients.installations.databases.update');
    Route::delete('/clientes/{client}/instalaciones/{installation}/bases/{database}', [InstallationDatabaseController::class, 'destroy'])->name('clients.installations.databases.destroy');
    Route::get('/infraestructura', [InfrastructureController::class, 'index'])->name('infrastructure.index');
    Route::post('/infraestructura', [InfrastructureController::class, 'store'])->name('infrastructure.store');
    Route::put('/infraestructura/{resource}', [InfrastructureController::class, 'update'])->name('infrastructure.update');
    Route::delete('/infraestructura/{resource}', [InfrastructureController::class, 'destroy'])->name('infrastructure.destroy');
    Route::post('/infraestructura/{resource}/participaciones', [InfrastructureController::class, 'storeShare'])->name('infrastructure.shares.store');
    Route::delete('/infraestructura/{resource}/participaciones/{share}', [InfrastructureController::class, 'destroyShare'])->name('infrastructure.shares.destroy');
    Route::get('/gastos-fijos', [FixedExpenseController::class, 'index'])->name('fixed-expenses.index');
    Route::post('/gastos-fijos', [FixedExpenseController::class, 'store'])->name('fixed-expenses.store');
    Route::post('/gastos-fijos/cotizacion', [FixedExpenseController::class, 'storeRate'])->name('fixed-expenses.rate.store');
    Route::post('/gastos-fijos/cotizacion/arq', [FixedExpenseController::class, 'refreshRate'])->middleware('throttle:10,1')->name('fixed-expenses.rate.refresh');
    Route::put('/gastos-fijos/{expense}', [FixedExpenseController::class, 'update'])->name('fixed-expenses.update');
    Route::delete('/gastos-fijos/{expense}', [FixedExpenseController::class, 'destroy'])->name('fixed-expenses.destroy');
    Route::post('/gastos-fijos/{expense}/importes', [FixedExpenseController::class, 'storeAmount'])->name('fixed-expenses.amounts.store');
    Route::delete('/gastos-fijos/{expense}/importes/{amount}', [FixedExpenseController::class, 'destroyAmount'])->name('fixed-expenses.amounts.destroy');
    Route::post('/clientes', [ClientController::class, 'store'])->name('clients.store');
    Route::put('/clientes/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::patch('/clientes/{client}/estado', [ClientController::class, 'toggle'])->name('clients.toggle');
    Route::post('/clientes/{client}/cobros', [ClientController::class, 'payment'])->name('clients.payment');
    Route::get('/movimientos', [MovementController::class, 'index'])->name('movements.index');
    Route::get('/movimientos/exportar.csv', [MovementController::class, 'export'])->name('movements.export');
    Route::post('/movimientos', [MovementController::class, 'store'])->name('movements.store');
    Route::put('/movimientos/{transaction}', [MovementController::class, 'update'])->name('movements.update');
    Route::delete('/movimientos/{transaction}', [MovementController::class, 'destroy'])->name('movements.destroy');
    Route::get('/finanzas-personales', [PersonalExpenseController::class, 'index'])->name('personal.index');
    Route::post('/finanzas-personales/gastos', [PersonalExpenseController::class, 'store'])->name('personal.expenses.store');
    Route::put('/finanzas-personales/gastos/{expense}', [PersonalExpenseController::class, 'update'])->name('personal.expenses.update');
    Route::delete('/finanzas-personales/gastos/{expense}', [PersonalExpenseController::class, 'destroy'])->name('personal.expenses.destroy');
    Route::put('/finanzas-personales/recurrentes/{recurring}', [PersonalRecurringExpenseController::class, 'update'])->name('personal.recurring.update');
    Route::delete('/finanzas-personales/recurrentes/{recurring}', [PersonalRecurringExpenseController::class, 'destroy'])->name('personal.recurring.destroy');
    Route::post('/finanzas-personales/recurrentes/{recurring}/importes', [PersonalRecurringExpenseController::class, 'storeAmount'])->name('personal.recurring.amounts.store');
    Route::delete('/finanzas-personales/recurrentes/{recurring}/importes/{amount}', [PersonalRecurringExpenseController::class, 'destroyAmount'])->name('personal.recurring.amounts.destroy');
});
