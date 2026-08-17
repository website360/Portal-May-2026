<?php

namespace App\Providers;

use App\Support\Smtp;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * O servidor de e-mail cadastrado passa a valer sobre o do .env.
         *
         * No momento em que alguém resolve o serviço de correio, e não no boot:
         * assim vale para tudo que manda e-mail — a tela, o agendamento, a
         * recuperação de senha — sem custar uma consulta ao banco nas outras
         * requisições, que são a maioria.
         */
        $this->app->resolving('mail.manager', fn () => Smtp::apply());
    }
}
