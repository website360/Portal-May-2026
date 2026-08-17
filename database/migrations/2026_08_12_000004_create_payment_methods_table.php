<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('color', 20)->default('blue');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        /*
         * `payment_method` era texto livre no lançamento. Vira referência, mas a
         * coluna antiga fica: apagar destruiria o que já foi digitado, e o texto
         * ainda serve de rótulo quando a forma não está cadastrada.
         */
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('payment_method')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::dropIfExists('payment_methods');
    }
};
