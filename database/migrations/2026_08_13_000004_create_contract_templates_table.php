<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();

            // O nome do modelo e o servico contratado sao a mesma coisa: e o
            // servico que decide qual contrato sai.
            $table->string('name')->unique();
            $table->string('description')->nullable();

            // O texto com {{marcadores}}. Os que o sistema conhece se preenchem
            // sozinhos; o resto vira campo do formulario na hora de gerar.
            $table->longText('body');

            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
