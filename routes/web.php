<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

/*
 * Convencao de modulos: cada modulo do sistema tem um arquivo em routes/modules/.
 * Todos sao carregados aqui dentro do grupo `auth`, entao criar um modulo novo
 * nunca exige editar este arquivo — basta adicionar routes/modules/<modulo>.php.
 */
Route::middleware(['auth'])->group(function () {
    foreach (glob(__DIR__.'/modules/*.php') as $module) {
        require $module;
    }
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
