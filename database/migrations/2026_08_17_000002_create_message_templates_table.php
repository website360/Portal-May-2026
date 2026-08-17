<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os modelos de mensagem do WhatsApp.
 *
 * O gatilho é uma chave do catálogo em código — cada um corresponde a um ponto
 * do sistema que manda mensagem. As regras decidem se o modelo serve para
 * aquele caso, e as variações existem para o cliente não receber sempre o mesmo
 * texto decorado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('trigger')->index();
            $table->string('name');
            $table->json('variations');
            $table->json('conditions')->nullable();
            // A maior é testada primeiro: a regra específica passa na frente da geral.
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
