<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos cadastrados direto (sem gerar/assinar documento) valem pela data,
 * não pela assinatura. Esta flag marca esse caso para o cálculo de status —
 * os contratos gerados continuam "rascunho até assinar".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('active_without_signature')->default(false)->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('active_without_signature');
        });
    }
};
