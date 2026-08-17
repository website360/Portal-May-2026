<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique();
            $table->string('registrar')->nullable();

            // Quem cuida da renovacao: a agencia ou o proprio cliente. E o que
            // separa "preciso ser avisado" de "so registro para referencia".
            $table->string('managed_by')->default('agency')->index();

            $table->date('registered_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->boolean('auto_renew')->default(false);
            $table->decimal('annual_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
