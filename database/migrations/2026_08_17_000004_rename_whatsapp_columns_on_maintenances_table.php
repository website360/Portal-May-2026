<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O aviso da manutenção deixa de ser necessariamente WhatsApp.
 *
 * Com o modelo de mensagem escolhendo os canais, "whatsapp_sent_at" passaria a
 * mentir no dia em que alguém montasse um modelo só de e-mail: o aviso teria
 * saído e a coluna diria que não.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->renameColumn('whatsapp_sent_at', 'notified_at');
            $table->renameColumn('whatsapp_error', 'notify_error');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->renameColumn('notified_at', 'whatsapp_sent_at');
            $table->renameColumn('notify_error', 'whatsapp_error');
        });
    }
};
