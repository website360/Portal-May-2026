<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Quando o cliente foi avisado do reajuste — muda o estado do botão.
            $table->timestamp('price_review_notified_at')->nullable()->after('price_review_years');
            // O novo valor enviado no aviso — fica registrado e pré-preenche a renovação.
            $table->decimal('price_review_new_value', 12, 2)->nullable()->after('price_review_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['price_review_notified_at', 'price_review_new_value']);
        });
    }
};
