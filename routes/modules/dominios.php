<?php

use App\Http\Controllers\Domains\DomainController;
use Illuminate\Support\Facades\Route;

Route::get('dominios', [DomainController::class, 'index'])->name('dominios.index');
Route::post('dominios', [DomainController::class, 'store'])->name('dominios.store');
Route::put('dominios/{dominio}', [DomainController::class, 'update'])->name('dominios.update');
Route::patch('dominios/{dominio}/gestao', [DomainController::class, 'updateManagement'])->name('dominios.gestao');
Route::delete('dominios/{dominio}', [DomainController::class, 'destroy'])->name('dominios.destroy');
