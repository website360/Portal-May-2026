<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplia `clients` com os campos do cadastro completo. A tabela nasceu minima
 * para o dashboard; o modulo de Clientes traz o resto, agrupado nas quatro
 * etapas do formulario: identificacao, contato, endereco e comercial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Etapa 1 — identificacao
            $table->string('type')->default('company')->after('id');
            $table->string('trade_name')->nullable()->after('name');
            $table->string('document')->nullable()->unique()->after('trade_name');

            // Etapa 2 — contato
            $table->string('phone')->nullable()->after('email');
            $table->string('contact_name')->nullable()->after('phone');
            $table->string('contact_role')->nullable()->after('contact_name');

            // Etapa 3 — endereco
            $table->string('zip_code', 9)->nullable()->after('contact_role');
            $table->string('street')->nullable()->after('zip_code');
            $table->string('number', 20)->nullable()->after('street');
            $table->string('complement')->nullable()->after('number');
            $table->string('district')->nullable()->after('complement');
            $table->string('city')->nullable()->after('district');
            $table->string('state', 2)->nullable()->after('city');

            // Etapa 4 — comercial
            $table->string('segment')->nullable()->after('state');
            $table->decimal('monthly_fee', 12, 2)->nullable()->after('segment');
            $table->date('started_at')->nullable()->after('monthly_fee');
            $table->text('notes')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['document']);

            $table->dropColumn([
                'type', 'trade_name', 'document',
                'phone', 'contact_name', 'contact_role',
                'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state',
                'segment', 'monthly_fee', 'started_at', 'notes',
            ]);
        });
    }
};
