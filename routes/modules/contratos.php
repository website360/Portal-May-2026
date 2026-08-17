<?php

use App\Http\Controllers\Contracts\ContractController;
use Illuminate\Support\Facades\Route;

Route::prefix('contratos')->name('contratos.')->group(function () {
    Route::get('/', [ContractController::class, 'index'])->name('index');
    Route::get('gerar', [ContractController::class, 'create'])->name('gerar');
    Route::post('/', [ContractController::class, 'store'])->name('store');
    /*
     * GET porque prévia é leitura: não grava nada, dispensa CSRF, e não exige
     * permissão de escrita — que o EnsureModuleAccess cobraria de um POST.
     */
    Route::get('previa', [ContractController::class, 'preview'])->name('previa');

    Route::put('{contrato}', [ContractController::class, 'update'])->name('update');
    Route::get('{contrato}/pdf', [ContractController::class, 'pdf'])->name('pdf');
    Route::post('{contrato}/assinatura', [ContractController::class, 'sign'])->name('assinatura');
    Route::post('{contrato}/cancelamento', [ContractController::class, 'cancel'])->name('cancelamento');
    Route::delete('{contrato}/arquivo', [ContractController::class, 'removeAttachment'])->name('arquivo.destroy');
    Route::delete('{contrato}', [ContractController::class, 'destroy'])->name('destroy');
});
