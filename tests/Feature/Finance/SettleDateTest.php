<?php

namespace Tests\Feature\Finance;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A data da baixa é informada, não assumida.
 *
 * Lançar no sistema raramente acontece no mesmo dia em que o dinheiro andou —
 * e assumir hoje jogaria a conta no mês errado, bagunçando "pago no mês" sem
 * ninguém perceber.
 */
class SettleDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_informed_date_is_what_gets_recorded(): void
    {
        $transaction = Transaction::factory()->payable()->dueIn(-10)->create(['amount' => 500]);

        $ontem = Carbon::today()->subDays(6)->toDateString();

        $this->patch("/financeiro/{$transaction->id}/situacao", [
            'status' => Transaction::STATUS_PAID,
            'paid_at' => $ontem,
        ])->assertSessionHasNoErrors();

        $this->assertSame($ontem, $transaction->refresh()->paid_at->toDateString());
    }

    /** Sem data informada, hoje continua sendo o padrão. */
    public function test_without_a_date_it_falls_back_to_today(): void
    {
        $transaction = Transaction::factory()->payable()->dueIn(-2)->create();

        $this->patch("/financeiro/{$transaction->id}/situacao", ['status' => Transaction::STATUS_PAID]);

        $this->assertSame(Carbon::today()->toDateString(), $transaction->refresh()->paid_at->toDateString());
    }

    /** O valor pago pode diferir do previsto — juros ou desconto. */
    public function test_the_settled_amount_can_differ_from_the_charge(): void
    {
        $transaction = Transaction::factory()->payable()->dueIn(-15)->create(['amount' => 500]);

        $this->patch("/financeiro/{$transaction->id}/situacao", [
            'status' => Transaction::STATUS_PAID,
            'paid_at' => Carbon::today()->toDateString(),
            'paid_amount' => '547.30',
        ]);

        $transaction->refresh();

        $this->assertSame('500.00', (string) $transaction->amount);
        $this->assertSame('547.30', (string) $transaction->paid_amount);
    }

    /**
     * O indicador "pago no mês" segue a data da baixa, não a do vencimento —
     * é por isso que informar a data certa importa.
     */
    public function test_the_month_indicator_follows_the_settlement_date(): void
    {
        $mesPassado = Carbon::today()->subMonthNoOverflow()->startOfMonth()->addDays(9);

        $transaction = Transaction::factory()->payable()->create(['due_date' => $mesPassado, 'amount' => 300]);

        $this->patch("/financeiro/{$transaction->id}/situacao", [
            'status' => Transaction::STATUS_PAID,
            'paid_at' => $mesPassado->toDateString(),
        ]);

        // Baixada no mês passado: não entra no "pago" do mês corrente.
        $this->assertSame(0, Transaction::query()->settledInMonth(Carbon::today()->format('Y-m'))->count());
        $this->assertSame(1, Transaction::query()->settledInMonth($mesPassado->format('Y-m'))->count());
    }

    public function test_reopening_clears_the_date_and_the_amount(): void
    {
        $transaction = Transaction::factory()->payable()->dueIn(-3)->paid()->create();

        $this->patch("/financeiro/{$transaction->id}/situacao", ['status' => Transaction::STATUS_PENDING]);

        $transaction->refresh();

        $this->assertNull($transaction->paid_at);
        $this->assertNull($transaction->paid_amount);
    }

    public function test_an_invalid_date_is_rejected(): void
    {
        $transaction = Transaction::factory()->payable()->dueIn(-3)->create();

        $this->patch("/financeiro/{$transaction->id}/situacao", [
            'status' => Transaction::STATUS_PAID,
            'paid_at' => 'ontem mesmo',
        ])->assertSessionHasErrors('paid_at');

        $this->assertNull($transaction->refresh()->paid_at);
    }
}
