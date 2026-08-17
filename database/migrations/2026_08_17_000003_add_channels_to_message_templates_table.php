<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O modelo de mensagem deixa de ser só do WhatsApp.
 *
 * O mesmo texto pode sair por mais de um canal e para mais de uma pessoa —
 * o cliente recebe no WhatsApp, o administrador recebe por e-mail. O assunto
 * só existe para e-mail, que é o único canal que tem um.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->json('channels')->nullable()->after('variations');
            $table->json('recipients')->nullable()->after('channels');
            $table->string('subject')->nullable()->after('recipients');
        });

        // Quem já existia era do WhatsApp para o cliente, e continua sendo.
        DB::table('message_templates')->update([
            'channels' => json_encode(['whatsapp']),
            'recipients' => json_encode(['client']),
        ]);
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn(['description', 'channels', 'recipients', 'subject']);
        });
    }
};
