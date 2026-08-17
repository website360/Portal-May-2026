<?php

use App\Http\Controllers\Clients\ClientController;
use Illuminate\Support\Facades\Route;

Route::get('clientes', [ClientController::class, 'index'])->name('clientes.index');
Route::post('clientes', [ClientController::class, 'store'])->name('clientes.store');
Route::get('clientes/{cliente}', [ClientController::class, 'show'])->name('clientes.show');
Route::put('clientes/{cliente}', [ClientController::class, 'update'])->name('clientes.update');
Route::patch('clientes/{cliente}/situacao', [ClientController::class, 'updateStatus'])->name('clientes.status');
Route::delete('clientes/{cliente}', [ClientController::class, 'destroy'])->name('clientes.destroy');
