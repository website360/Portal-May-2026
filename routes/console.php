<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Recorrências viram lançamentos de madrugada, com 30 dias de antecedência: a
 * conta aparece no financeiro antes de vencer, e não no dia.
 *
 * `withoutOverlapping` porque a geração é idempotente mas não precisa correr
 * duas vezes junto; se o servidor ficar dias fora do ar, a próxima execução
 * recupera os vencimentos perdidos sozinha.
 */
Schedule::command('financeiro:gerar-recorrencias --dias=30')
    ->dailyAt('03:00')
    ->withoutOverlapping();
