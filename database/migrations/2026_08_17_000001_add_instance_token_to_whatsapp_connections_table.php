<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O Evolution Go autentica em dois niveis: a chave global gerencia as
     * instancias, e cada instancia tem um token proprio que autentica pedir o
     * QR, consultar estado e enviar mensagem. Sem guardar esse token, so daria
     * para criar instancias — nunca usa-las.
     */
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->text('instance_token')->nullable()->after('api_key');
            $table->string('instance_id')->nullable()->after('instance_token');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->dropColumn(['instance_token', 'instance_id']);
        });
    }
};
