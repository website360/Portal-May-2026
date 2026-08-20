<?php

use App\Http\Controllers\Contracts\ContractController;
use Illuminate\Support\Facades\Route;

Route::prefix('contratos')->name('contratos.')->group(function () {
    Route::get('/', [ContractController::class, 'index'])->name('index');
    Route::get('gerar', [ContractController::class, 'create'])->name('gerar');
    Route::post('/', [ContractController::class, 'store'])->name('store');
    // Cadastro direto de um contrato já existente, sem gerar documento.
    Route::post('registrar', [ContractController::class, 'register'])->name('registrar');
    /*
     * GET porque prévia é leitura: não grava nada, dispensa CSRF, e não exige
     * permissão de escrita — que o EnsureModuleAccess cobraria de um POST.
     */
    Route::get('previa', [ContractController::class, 'preview'])->name('previa');

    Route::post('{contrato}/renovacao', [ContractController::class, 'renew'])->name('renovacao');
    // Avisa o cliente do reajuste (e-mail) antes de aplicá-lo.
    Route::post('{contrato}/reajuste/aviso', [ContractController::class, 'notifyPriceReview'])->name('reajuste.aviso');
    Route::put('{contrato}', [ContractController::class, 'update'])->name('update');
    Route::get('{contrato}/pdf', [ContractController::class, 'pdf'])->name('pdf');
    Route::post('{contrato}/assinatura', [ContractController::class, 'sign'])->name('assinatura');
    Route::post('{contrato}/cancelamento', [ContractController::class, 'cancel'])->name('cancelamento');
    Route::delete('{contrato}/arquivo', [ContractController::class, 'removeAttachment'])->name('arquivo.destroy');
    Route::delete('{contrato}', [ContractController::class, 'destroy'])->name('destroy');
});
