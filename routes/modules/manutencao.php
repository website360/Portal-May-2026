<?php

use App\Http\Controllers\Maintenance\MaintenanceController;
use App\Http\Controllers\Maintenance\MaintenancePlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('manutencao')->name('manutencao.')->group(function () {
    Route::get('/', [MaintenancePlanController::class, 'index'])->name('index');

    Route::post('planos', [MaintenancePlanController::class, 'store'])->name('planos.store');
    Route::put('planos/{plano}', [MaintenancePlanController::class, 'update'])->name('planos.update');
    Route::delete('planos/{plano}', [MaintenancePlanController::class, 'destroy'])->name('planos.destroy');

    Route::post('planos/{plano}/registros', [MaintenanceController::class, 'store'])->name('registros.store');
    Route::put('registros/{manutencao}', [MaintenanceController::class, 'update'])->name('registros.update');
    Route::post('registros/{manutencao}/reenviar', [MaintenanceController::class, 'resend'])->name('registros.reenviar');
    Route::delete('registros/{manutencao}', [MaintenanceController::class, 'destroy'])->name('registros.destroy');
});
