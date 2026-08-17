<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // 'payable' (a pagar) ou 'receivable' (a receber).
            $table->string('type')->index();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->index();

            // Pago não é um campo de situação: a situação sai da data. Sem data,
            // está em aberto; com data, está paga.
            $table->date('paid_at')->nullable()->index();
            $table->decimal('paid_amount', 12, 2)->nullable();

            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finance_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->string('counterpart')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();

            // Agrupa as parcelas geradas de uma vez, para dar para reconhecer a série.
            $table->uuid('series_id')->nullable()->index();
            $table->unsignedSmallInteger('installment')->nullable();
            $table->unsignedSmallInteger('installments')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
