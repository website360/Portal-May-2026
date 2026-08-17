<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();

            // Quem executou. Fica nulo se a pessoa sair da agencia: o registro da
            // manutencao vale por si, mesmo sem o autor.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->date('performed_at')->index();

            /*
             * O checklist inteiro, item a item, com o rotulo que ele tinha no dia.
             * Guardar so a chave faria um item renomeado reescrever o historico —
             * o relatorio que o cliente recebeu nao muda depois de enviado.
             */
            $table->json('items');

            $table->text('notes')->nullable();

            // Do envio do relatorio: quando saiu, ou por que nao saiu.
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->string('whatsapp_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
