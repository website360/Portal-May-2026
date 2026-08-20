<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O histórico de renovações do contrato. Cada entrada guarda a data e o antes/
 * depois de vigência e valor — renovar estende o mesmo contrato, mas o que
 * mudou fica registrado, para se ver os períodos e valores anteriores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->json('renewals')->nullable()->after('price_review_years');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('renewals');
        });
    }
};
