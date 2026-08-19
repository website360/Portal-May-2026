<?php

use App\Http\Controllers\Finance\ReconciliationController;
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

Route::get('financeiro/conciliacao', [ReconciliationController::class, 'index'])->name('financeiro.conciliacao');
Route::post('financeiro/conciliacao/{lancamento}/baixa', [ReconciliationController::class, 'darBaixa'])->name('financeiro.conciliacao.baixa');
Route::post('financeiro/conciliacao/conciliar', [ReconciliationController::class, 'conciliar'])->name('financeiro.conciliacao.conciliar');
Route::post('financeiro/conciliacao/criar-no-asaas', [ReconciliationController::class, 'criarNoAsaas'])->name('financeiro.conciliacao.criar-asaas');
Route::post('financeiro/conciliacao/criar-lancamento', [ReconciliationController::class, 'criarLancamento'])->name('financeiro.conciliacao.criar-lancamento');
Route::get('financeiro', [TransactionController::class, 'index'])->name('financeiro.index');
Route::get('financeiro/cobrancas', [TransactionController::class, 'cobrancas'])->name('financeiro.cobrancas');
Route::get('financeiro/{lancamento}/cobrar/previa', [TransactionController::class, 'cobrancaPrevia'])->name('financeiro.cobrar.previa');
Route::get('financeiro/cobrar-vencidas/previa', [TransactionController::class, 'cobrarVencidasPrevia'])->name('financeiro.cobrar-vencidas.previa');
Route::post('financeiro/cobrar-vencidas', [TransactionController::class, 'cobrarVencidas'])->name('financeiro.cobrar-vencidas');
Route::post('financeiro/{lancamento}/cobrar', [TransactionController::class, 'cobrar'])->name('financeiro.cobrar');
Route::put('financeiro/{lancamento}/etiquetas', [TransactionController::class, 'etiquetas'])->name('financeiro.etiquetas');
Route::post('financeiro', [TransactionController::class, 'store'])->name('financeiro.store');
Route::put('financeiro/{lancamento}', [TransactionController::class, 'update'])->name('financeiro.update');
Route::delete('financeiro/{lancamento}', [TransactionController::class, 'destroy'])->name('financeiro.destroy');

// Baixa e estorno pelo seletor inline da listagem.
Route::patch('financeiro/{lancamento}/situacao', [TransactionController::class, 'updateStatus'])->name('financeiro.status');

