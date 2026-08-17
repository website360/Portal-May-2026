<?php

namespace Tests\Feature\Finance;

use App\Models\CostCenter;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_generating_creates_the_transaction_and_advances_the_next_due_date(): void
    {
        $recurrence = Recurrence::factory()->annual()->create([
            'description' => 'Hospedagem anual',
            'amount' => 1200,
            'next_due_at' => '2026-03-10',
        ]);

        $transaction = $recurrence->generateNext();

        $this->assertNotNull($transaction);
        $this->assertSame('Hospedagem anual', $transaction->description);
        $this->assertSame('1200.00', (string) $transaction->amount);
        $this->assertSame('2026-03-10', $transaction->due_date->toDateString());
        $this->assertSame($recurrence->id, $transaction->recurrence_id);

        $this->assertSame('2027-03-10', $recurrence->refresh()->next_due_at->toDateString());
    }

    public function test_each_interval_advances_by_its_own_step(): void
    {
        $steps = [
            Recurrence::MONTHLY => '2026-04-10',
            Recurrence::QUARTERLY => '2026-06-10',
            Recurrence::SEMIANNUAL => '2026-09-10',
            Recurrence::ANNUAL => '2027-03-10',
        ];

        foreach ($steps as $interval => $expected) {
            $this->assertSame($expected, Recurrence::advance(Carbon::parse('2026-03-10'), $interval)->toDateString());
        }
    }

    /**
     * Uma conta que vence todo dia 31 não pode escorregar de mês porque
     * fevereiro é curto — addMonths puro levaria 31/01 para 03/03.
     */
    public function test_the_end_of_month_does_not_slip_into_the_next_one(): void
    {
        $this->assertSame('2026-02-28', Recurrence::advance(Carbon::parse('2026-01-31'), Recurrence::MONTHLY)->toDateString());
        $this->assertSame('2026-04-30', Recurrence::advance(Carbon::parse('2026-03-31'), Recurrence::MONTHLY)->toDateString());
    }

    /** Rodar duas vezes no mesmo vencimento não pode duplicar a conta. */
    public function test_generating_twice_for_the_same_due_date_creates_one_transaction(): void
    {
        $recurrence = Recurrence::factory()->create(['next_due_at' => '2026-03-10']);

        $recurrence->generateNext();
        $recurrence->update(['next_due_at' => '2026-03-10']);
        $second = $recurrence->generateNext();

        $this->assertNull($second);
        $this->assertSame(1, Transaction::count());
    }

    public function test_an_inactive_recurrence_generates_nothing(): void
    {
        $recurrence = Recurrence::factory()->inactive()->create(['next_due_at' => now()->subDay()->toDateString()]);

        $this->assertNull($recurrence->generateNext());
        $this->assertSame(0, Transaction::count());
    }

    public function test_a_recurrence_stops_after_its_end_date(): void
    {
        $recurrence = Recurrence::factory()->annual()->create([
            'next_due_at' => '2026-03-10',
            'ends_at' => '2026-06-30',
        ]);

        // O vencimento de março ainda cabe...
        $this->assertNotNull($recurrence->generateNext());

        // ...mas o próximo é março de 2027, além do fim.
        $this->assertTrue($recurrence->refresh()->hasEnded());
        $this->assertNull($recurrence->generateNext());
        $this->assertSame(1, Transaction::count());
    }

    public function test_the_command_generates_what_is_due_inside_the_window(): void
    {
        Recurrence::factory()->annual()->dueIn(10)->create(['description' => 'Dentro da janela']);
        Recurrence::factory()->annual()->dueIn(90)->create(['description' => 'Longe demais']);

        $this->artisan('financeiro:gerar-recorrencias', ['--dias' => 30])->assertSuccessful();

        $this->assertSame(1, Transaction::count());
        $this->assertSame('Dentro da janela', Transaction::sole()->description);
    }

    /** Recorrência esquecida há meses recupera todos os vencimentos perdidos. */
    public function test_a_stale_recurrence_catches_up_on_every_missed_due_date(): void
    {
        Recurrence::factory()->monthly()->create([
            'description' => 'Mensalidade',
            'next_due_at' => now()->subMonths(3)->toDateString(),
        ]);

        $this->artisan('financeiro:gerar-recorrencias', ['--dias' => 0])->assertSuccessful();

        // Três meses atrás, dois, um, e o do mês corrente.
        $this->assertGreaterThanOrEqual(3, Transaction::count());
        $this->assertSame(Transaction::count(), Transaction::distinct('due_date')->count('due_date'));
    }

    public function test_running_the_command_twice_does_not_duplicate(): void
    {
        Recurrence::factory()->annual()->dueIn(5)->create();

        $this->artisan('financeiro:gerar-recorrencias');
        $this->artisan('financeiro:gerar-recorrencias');

        $this->assertSame(1, Transaction::count());
    }

    public function test_the_dry_run_writes_nothing(): void
    {
        Recurrence::factory()->annual()->dueIn(5)->create();
        $before = Recurrence::sole()->next_due_at->toDateString();

        $this->artisan('financeiro:gerar-recorrencias', ['--dry-run' => true])
            ->expectsOutputToContain('SIMULAÇÃO')
            ->assertSuccessful();

        $this->assertSame(0, Transaction::count());
        $this->assertSame($before, Recurrence::sole()->next_due_at->toDateString());
    }

    public function test_the_generated_transaction_carries_the_classification(): void
    {
        $center = CostCenter::factory()->create();

        $recurrence = Recurrence::factory()->receivable()->create([
            'cost_center_id' => $center->id,
            'counterpart' => 'Locaweb',
        ]);

        $transaction = $recurrence->generateNext();

        $this->assertSame(Transaction::TYPE_RECEIVABLE, $transaction->type);
        $this->assertSame($center->id, $transaction->cost_center_id);
        $this->assertSame('Locaweb', $transaction->counterpart);
    }

    /** A conta gerada é uma conta normal: entra nos indicadores como qualquer outra. */
    public function test_the_generated_transaction_shows_up_in_the_finance_list(): void
    {
        Recurrence::factory()->annual()->dueIn(3)->create(['description' => 'Domínio agenciamay.com.br']);

        $this->artisan('financeiro:gerar-recorrencias');

        $this->get('/financeiro')->assertInertia(
            fn ($page) => $page->where('transactions.data.0.description', 'Domínio agenciamay.com.br')
        );
    }
}
