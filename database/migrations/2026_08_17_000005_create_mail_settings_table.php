<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O servidor de e-mail da agência.
 *
 * Fica no banco, e não no .env, pela mesma razão da conexão do WhatsApp: quem
 * troca a senha do e-mail é quem usa o sistema, e não quem faz deploy. Uma
 * linha só — a agência manda de um endereço.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username')->nullable();
            // Guardada cifrada pelo cast do modelo.
            $table->text('password')->nullable();
            $table->string('encryption')->nullable();
            $table->string('from_address');
            $table->string('from_name');
            $table->boolean('active')->default(false);
            $table->timestamp('tested_at')->nullable();
            $table->string('test_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
