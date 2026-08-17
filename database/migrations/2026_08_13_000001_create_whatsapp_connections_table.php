<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();

            // Endereço do servidor Evolution e o nome da instância dentro dele.
            $table->string('base_url');
            $table->string('instance');

            /*
             * A chave é gravada cifrada pelo cast do model. Ela dá acesso total
             * ao servidor Evolution — inclusive a enviar mensagem em nome da
             * agência — então não pode ficar legível no banco.
             */
            $table->text('api_key');

            // Estado da última verificação; a verdade continua sendo o servidor.
            $table->string('status')->default('disconnected');
            $table->string('number')->nullable();
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connections');
    }
};
