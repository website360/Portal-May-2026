<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dois ritmos que o contrato passa a acompanhar:
 *  - billing_period: mensal ou anual, o ciclo contratado (guia a renovação).
 *  - price_review_at + price_review_years: quando reajustar o preço e de quantos
 *    em quantos anos — padrão bianual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('billing_period')->nullable()->after('ends_at');
            $table->date('price_review_at')->nullable()->after('billing_period');
            $table->unsignedTinyInteger('price_review_years')->default(2)->after('price_review_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['billing_period', 'price_review_at', 'price_review_years']);
        });
    }
};
