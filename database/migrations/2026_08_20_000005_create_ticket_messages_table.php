<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Autor da equipe; nulo quando a mensagem veio do cliente (canais externos).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('body');
            // Nota interna: só a equipe vê, não vai para o cliente.
            $table->boolean('internal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
