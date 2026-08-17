<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            /*
             * Contrato que vai para assinatura eletronica nao leva linha de
             * assinatura: quem assina e a plataforma, e o campo em branco no
             * papel so confunde. Contrato impresso precisa dela.
             */
            $table->boolean('with_signatures')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn('with_signatures');
        });
    }
};
