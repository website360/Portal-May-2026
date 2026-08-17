<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            // O modelo que originou. Fica nulo quando o contrato so foi anexado
            // em PDF, ou quando o modelo for excluido depois — o contrato ja
            // assinado nao depende mais dele.
            $table->foreignId('contract_template_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number')->unique();
            $table->string('title');

            // Copia do nome do servico no dia: renomear o modelo nao reescreve
            // o que foi contratado.
            $table->string('service');

            $table->decimal('value', 12, 2)->nullable();
            $table->date('starts_at');
            $table->date('ends_at')->nullable()->index();

            /*
             * O texto final, ja com os marcadores trocados, e os valores que
             * foram usados. Guardar os dois permite reimprimir identico e ainda
             * mostrar o que foi preenchido.
             */
            $table->longText('body')->nullable();
            $table->json('variables')->nullable();

            $table->string('pdf_path')->nullable();
            $table->date('signed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
