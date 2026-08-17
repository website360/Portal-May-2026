<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quem assina pelo cliente.
     *
     * Separado do contato do dia a dia de proposito: o contato pode ser quem
     * cuida do marketing, e o contrato precisa de quem responde juridicamente
     * — com o CPF, que a qualificacao das partes exige.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('representative_name')->nullable()->after('contact_role');
            $table->string('representative_role')->nullable()->after('representative_name');
            $table->string('representative_document')->nullable()->after('representative_role');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['representative_name', 'representative_role', 'representative_document']);
        });
    }
};
