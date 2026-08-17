<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // O site atendido. Um cliente pode ter mais de um contrato de
            // manutencao, um por site — por isso a chave e o par, nao o cliente.
            $table->string('site_url');

            /*
             * A manutencao e mensal e o dia dentro do mes e livre, entao nao ha
             * data de vencimento a guardar: o que importa e se o mes corrente ja
             * teve a sua. Esta coluna e cache da ultima registrada, recalculada
             * por refreshSchedule() sempre que uma entra ou sai — a situacao
             * sempre deriva dela, nunca fica gravada.
             */
            $table->date('last_performed_at')->nullable()->index();

            $table->boolean('active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'site_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};
