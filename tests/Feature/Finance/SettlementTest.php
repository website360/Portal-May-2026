<?php

namespace Tests\Feature\Finance;

use App\Models\CostCenter;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Baixa, estorno e a situação derivada — o coração do módulo.
 */
class SettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function transaction(int $dueInDays, bool $paid = false): Transaction
    {
        $factory = Transaction::factory()->payable()->state([
            'amount' => 1000,
            'due_date' => now()->addDays($dueInDays),
            'cost_center_id' => CostCenter::factory(),
        ]);

        return $paid ? $factory->paid()->create() : $factory->create();
    }

    public function test_the_status_comes_from_the_due_date_and_the_settlement(): void
    {
        $this->assertSame(Transaction::STATUS_PENDING, $this->transaction(5)->status());
        $this->assertSame(Transaction::STATUS_PENDING, $this->transaction(0)->status());
        $this->assertSame(Transaction::STATUS_OVERDUE, $this->transaction(-1)->status());
        // Paga no passado continua paga: a baixa manda sobre o vencimento.
        $this->assertSame(Transaction::STATUS_PAID, $this->transaction(-30, paid: true)->status());
    }

    public function test_the_sql_filter_matches_the_computed_status(): void
    {
        $this->transaction(5);
        $this->transaction(-2);
        $this->transaction(-30, paid: true);

        $this->assertSame(1, Transaction::query()->withStatus(Transaction::STATUS_PENDING)->count());
        $this->assertSame(1, Transaction::query()->withStatus(Transaction::STATUS_OVERDUE)->count());
        $this->assertSame(1, Transaction::query()->withStatus(Transaction::STATUS_PAID)->count());
    }

    public function test_settling_records_the_date_and_the_amount(): void
    {
        $transaction = $this->transaction(3);

        $this->patch("/financeiro/{$transaction->id}/situacao", ['status' => Transaction::STATUS_PAID])->assertSessionHasNoErrors();

        $transaction->refresh();

        $this->assertNotNull($transaction->paid_at);
        $this->assertSame('1000.00', $transaction->paid_amount);
        $this->assertSame(Transaction::STATUS_PAID, $transaction->status());
    }

    /** Juros ou desconto: o valor pago pode diferir do previsto. */
    public function test_settling_accepts_a_different_amount(): void
    {
        $transaction = $this->transaction(-10);

        $this->patch("/financeiro/{$transaction->id}/situacao", [
            'status' => Transaction::STATUS_PAID,
            'paid_at' => now()->toDateString(),
            'paid_amount' => 1042.50,
        ])->assertSessionHasNoErrors();

        $this->assertSame('1042.50', $transaction->refresh()->paid_amount);
        $this->assertSame(1042.50, $transaction->settledAmount());
    }

    public function test_reopening_clears_the_settlement(): void
    {
        $transaction = $this->transaction(-5, paid: true);

        $this->patch("/financeiro/{$transaction->id}/situacao", ['status' => Transaction::STATUS_PENDING])->assertSessionHasNoErrors();

        $transaction->refresh();

        $this->assertNull($transaction->paid_at);
        $this->assertNull($transaction->paid_amount);
        // Voltou a estar vencida, porque o vencimento já passou.
        $this->assertSame(Transaction::STATUS_OVERDUE, $transaction->status());
    }

    /** "Vencida" é consequência, não escolha: não dá para marcar à mão. */
    public function test_overdue_can_not_be_set_by_hand(): void
    {
        $transaction = $this->transaction(5);

        $this->patch("/financeiro/{$transaction->id}/situacao", ['status' => Transaction::STATUS_OVERDUE])->assertSessionHasErrors('status');

        $this->assertNull($transaction->refresh()->paid_at);
    }

    public function test_the_settlement_route_ignores_other_fields(): void
    {
        $transaction = $this->transaction(5);

        $this->patch("/financeiro/{$transaction->id}/situacao", [
            'status' => Transaction::STATUS_PAID,
            'amount' => 999999,
            'description' => 'invadido',
        ])->assertSessionHasNoErrors();

        $transaction->refresh();

        $this->assertSame('1000.00', $transaction->amount);
        $this->assertNotSame('invadido', $transaction->description);
    }
}
