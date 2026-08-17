<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Categoria pertence a uma natureza: "Aluguel" é despesa, "Serviços"
            // é receita. O formulário só oferece as do tipo do lançamento.
            $table->string('type')->index();
            $table->string('color')->default('blue');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_categories');
    }
};
