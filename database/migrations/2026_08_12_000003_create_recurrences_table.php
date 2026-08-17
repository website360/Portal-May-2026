<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();

            $table->string('type')->index();
            $table->string('description');
            $table->decimal('amount', 12, 2);

            // 'monthly', 'quarterly', 'semiannual', 'annual'.
            $table->string('interval', 20)->index();

            /*
             * Data do próximo vencimento. É o estado da recorrência: gerar um
             * lançamento avança este campo, e é só por ele que o sistema sabe
             * onde parou — sem precisar recalcular a série desde o começo.
             */
            $table->date('next_due_at')->index();

            // Quando parar. Nulo = enquanto estiver ativa.
            $table->date('ends_at')->nullable();

            $table->boolean('active')->default(true)->index();

            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finance_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            $table->string('counterpart')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurrence_id')->nullable()->after('series_id')->constrained()->nullOnDelete();

            /*
             * Uma recorrência só pode gerar um lançamento por vencimento. Sem
             * esta trava, rodar a geração duas vezes no mesmo dia — ou um
             * agendamento que dispara em duplicidade — criaria a mesma conta
             * repetida, e o erro só apareceria no fechamento do mês.
             */
            $table->unique(['recurrence_id', 'due_date'], 'transactions_recurrence_due_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_recurrence_due_unique');
            $table->dropConstrainedForeignId('recurrence_id');
        });

        Schema::dropIfExists('recurrences');
    }
};
