<?php

use App\Http\Controllers\Settings\ContractTemplateController;
use App\Http\Controllers\Settings\CostCenterController;
use App\Http\Controllers\Settings\FinanceCategoryController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PaymentMethodController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SupplierController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\WhatsappController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * Configurações do sistema. Reúne o que é da conta de quem usa (perfil, senha,
 * aparência) e o que é do sistema (financeiro), num lugar só — fora da navegação
 * dos módulos, que é para o trabalho do dia a dia.
 */
Route::middleware('auth')->prefix('configuracoes')->group(function () {
    Route::redirect('/', '/configuracoes/perfil');

    Route::get('perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('senha', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('senha', [PasswordController::class, 'update'])->name('password.update');

    Route::get('aparencia', fn () => Inertia::render('settings/appearance'))->name('appearance');

    /*
     * Só administradores. Não basta ter escrita em Configurações: quem abre
     * esta tela pode se promover, o que faria daquela permissão um atalho para
     * acesso total.
     */
    Route::middleware(EnsureAdmin::class)->name('configuracoes.')->group(function () {
        Route::get('whatsapp', [WhatsappController::class, 'index'])->name('whatsapp.index');
        Route::put('whatsapp', [WhatsappController::class, 'update'])->name('whatsapp.update');
        Route::get('whatsapp/qrcode', [WhatsappController::class, 'qrCode'])->name('whatsapp.qrcode');
        Route::get('whatsapp/estado', [WhatsappController::class, 'state'])->name('whatsapp.estado');
        Route::delete('whatsapp', [WhatsappController::class, 'disconnect'])->name('whatsapp.disconnect');

        Route::get('usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::post('usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::put('usuarios/{usuario}', [UserController::class, 'update'])->name('usuarios.update');
        Route::delete('usuarios/{usuario}', [UserController::class, 'destroy'])->name('usuarios.destroy');
    });

    /*
     * Modelos de contrato. Não são admin-only: são conteúdo de negócio, como
     * as categorias e as formas de pagamento, e quem escreve em Configurações
     * pode mexer.
     */
    Route::name('configuracoes.')->group(function () {
        Route::get('modelos-de-contrato', [ContractTemplateController::class, 'index'])->name('modelos.index');
        Route::post('modelos-de-contrato', [ContractTemplateController::class, 'store'])->name('modelos.store');
        Route::put('modelos-de-contrato/{modelo}', [ContractTemplateController::class, 'update'])->name('modelos.update');
        Route::delete('modelos-de-contrato/{modelo}', [ContractTemplateController::class, 'destroy'])->name('modelos.destroy');
    });

    Route::name('configuracoes.')->prefix('financeiro')->group(function () {
        Route::get('centros-de-custo', [CostCenterController::class, 'index'])->name('centros.index');
        Route::post('centros-de-custo', [CostCenterController::class, 'store'])->name('centros.store');
        Route::put('centros-de-custo/{centro}', [CostCenterController::class, 'update'])->name('centros.update');
        Route::delete('centros-de-custo/{centro}', [CostCenterController::class, 'destroy'])->name('centros.destroy');

        Route::get('fornecedores', [SupplierController::class, 'index'])->name('fornecedores.index');
        Route::post('fornecedores', [SupplierController::class, 'store'])->name('fornecedores.store');
        Route::put('fornecedores/{fornecedor}', [SupplierController::class, 'update'])->name('fornecedores.update');
        Route::delete('fornecedores/{fornecedor}', [SupplierController::class, 'destroy'])->name('fornecedores.destroy');

        Route::get('formas-de-pagamento', [PaymentMethodController::class, 'index'])->name('formas.index');
        Route::post('formas-de-pagamento', [PaymentMethodController::class, 'store'])->name('formas.store');
        Route::put('formas-de-pagamento/{forma}', [PaymentMethodController::class, 'update'])->name('formas.update');
        Route::delete('formas-de-pagamento/{forma}', [PaymentMethodController::class, 'destroy'])->name('formas.destroy');

        Route::get('categorias', [FinanceCategoryController::class, 'index'])->name('categorias.index');
        Route::post('categorias', [FinanceCategoryController::class, 'store'])->name('categorias.store');
        Route::put('categorias/{categoria}', [FinanceCategoryController::class, 'update'])->name('categorias.update');
        Route::delete('categorias/{categoria}', [FinanceCategoryController::class, 'destroy'])->name('categorias.destroy');
    });
});
