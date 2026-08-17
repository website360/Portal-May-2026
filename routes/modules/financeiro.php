<?php

use App\Http\Controllers\Finance\RecurrenceController;
use App\Http\Controllers\Finance\TransactionController;
use Illuminate\Support\Facades\Route;

/*
 * Recorrências antes do resto: `financeiro/{lancamento}` casaria com
 * `financeiro/recorrencias` e a lista nunca abriria.
 */
Route::get('financeiro/recorrencias', [RecurrenceController::class, 'index'])->name('financeiro.recorrencias.index');
Route::put('financeiro/recorrencias/{recorrencia}', [RecurrenceController::class, 'update'])->name('financeiro.recorrencias.update');
Route::post('financeiro/recorrencias/{recorrencia}/renovar', [RecurrenceController::class, 'renew'])->name('financeiro.recorrencias.renovar');
Route::delete('financeiro/recorrencias/{recorrencia}', [RecurrenceController::class, 'destroy'])->name('financeiro.recorrencias.destroy');

Route::get('financeiro', [TransactionController::class, 'index'])->name('financeiro.index');
Route::post('financeiro', [TransactionController::class, 'store'])->name('financeiro.store');
Route::put('financeiro/{lancamento}', [TransactionController::class, 'update'])->name('financeiro.update');
Route::delete('financeiro/{lancamento}', [TransactionController::class, 'destroy'])->name('financeiro.destroy');

// Baixa e estorno pelo seletor inline da listagem.
Route::patch('financeiro/{lancamento}/situacao', [TransactionController::class, 'updateStatus'])->name('financeiro.status');
