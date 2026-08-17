<?php

use App\Http\Controllers\Tools\ToolController;
use Illuminate\Support\Facades\Route;

Route::prefix('ferramentas')->name('ferramentas.')->group(function () {
    Route::get('/', [ToolController::class, 'index'])->name('index');

    Route::get('boleto', [ToolController::class, 'boleto'])->name('boleto');
    // GET porque calcular e leitura: nao grava nada e dispensa CSRF.
    Route::get('boleto/calculo', [ToolController::class, 'calcularBoleto'])->name('boleto.calculo');
});
