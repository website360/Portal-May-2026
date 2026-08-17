<?php

namespace Tests\Feature\Finance;

use App\Models\Client;
use App\Models\CostCenter;
use App\Models\FinanceCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private CostCenter $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->center = CostCenter::factory()->create(['name' => 'Escritório']);
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => Transaction::TYPE_PAYABLE,
            'description' => 'Aluguel do escritório',
            'amount' => '4200.00',
            'due_date' => now()->addDays(10)->toDateString(),
            'cost_center_id' => $this->center->id,
        ], $overrides);
    }

    public function test_guests_can_not_reach_the_module(): void
    {
        $this->app['auth']->logout();

        $this->get('/financeiro')->assertRedirect(route('login'));
        $this->post('/financeiro', $this->payload())->assertRedirect(route('login'));
    }

    /**
     * Dois trios espelhados: total do período, o que já foi baixado e o que
     * ainda pesa (atrasado a pagar, em aberto a receber).
     */
    public function test_the_summary_mirrors_both_directions(): void
    {
        Transaction::factory(2)->payable()->dueIn(5)->create(['amount' => 100, 'cost_center_id' => $this->center->id]);
        Transaction::factory()->payable()->overdue()->create(['amount' => 300, 'cost_center_id' => $this->center->id]);
        Transaction::factory()->payable()->dueIn(-3)->paid()->create(['amount' => 250, 'cost_center_id' => $this->center->id]);

        Transaction::factory()->receivable()->dueIn(5)->create(['amount' => 500, 'cost_center_id' => $this->center->id]);
        Transaction::factory()->receivable()->dueIn(-2)->paid()->create(['amount' => 900, 'cost_center_id' => $this->center->id]);

        $money = fn (float $expected) => fn ($value) => (float) $value === $expected;

        $this->get('/financeiro?month=')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('financeiro/index')
                    ->has('transactions.data', 6)
                    // A pagar: 100 + 100 + 300 + 250
                    ->where('summary.payable.total.amount', $money(750.0))
                    ->where('summary.payable.total.count', 4)
                    ->where('summary.payable.paid.amount', $money(250.0))
                    ->where('summary.payable.overdue.amount', $money(300.0))
                    ->where('summary.payable.overdue.count', 1)
                    // A receber: 500 em aberto + 900 recebido
                    ->where('summary.receivable.total.amount', $money(1400.0))
                    ->where('summary.receivable.paid.amount', $money(900.0))
                    ->where('summary.receivable.open.amount', $money(500.0))
                    ->has('costCenters')
                    ->has('categories')
                    ->has('months')
            );
    }

    /** Os indicadores seguem o mesmo período da listagem, senão não fecham. */
    public function test_the_summary_follows_the_month_filter(): void
    {
        Transaction::factory()->payable()->create(['amount' => 100, 'due_date' => '2026-03-10', 'cost_center_id' => $this->center->id]);
        Transaction::factory()->payable()->create(['amount' => 700, 'due_date' => '2026-04-10', 'cost_center_id' => $this->center->id]);

        $this->get('/financeiro?month=2026-03')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('summary.month', '2026-03')
                ->where('summary.payable.total.amount', fn ($v) => (float) $v === 100.0)
                ->where('summary.payable.total.count', 1)
        );

        $this->get('/financeiro?month=')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('summary.month', '')
                ->where('summary.payable.total.amount', fn ($v) => (float) $v === 800.0)
        );
    }

    /** "Baixado" usa o valor efetivamente pago, não o previsto. */
    public function test_the_settled_total_uses_the_amount_actually_paid(): void
    {
        Transaction::factory()->payable()->dueIn(-5)->create([
            'amount' => 1000,
            'paid_at' => now()->subDays(3),
            'paid_amount' => 1042.50,
            'cost_center_id' => $this->center->id,
        ]);

        $this->get('/financeiro?month=')->assertInertia(
            fn (AssertableInertia $page) => $page->where('summary.payable.paid.amount', fn ($v) => (float) $v === 1042.50)
        );
    }

    public function test_the_open_status_covers_everything_not_settled(): void
    {
        Transaction::factory()->receivable()->dueIn(5)->create(['cost_center_id' => $this->center->id]);
        Transaction::factory()->receivable()->overdue()->create(['cost_center_id' => $this->center->id]);
        Transaction::factory()->receivable()->dueIn(-5)->paid()->create(['cost_center_id' => $this->center->id]);

        // month vazio: a vencida cai no mês passado, e o padrão agora é o mês corrente.
        $this->get('/financeiro?status=open&month=')->assertInertia(fn (AssertableInertia $page) => $page->has('transactions.data', 2));
    }

    public function test_a_transaction_can_be_created(): void
    {
        $this->post('/financeiro', $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Aluguel do escritório',
            'type' => Transaction::TYPE_PAYABLE,
            'cost_center_id' => $this->center->id,
            'paid_at' => null,
        ]);
    }

    public function test_the_cost_center_is_required(): void
    {
        $this->post('/financeiro', $this->payload(['cost_center_id' => null]))->assertSessionHasErrors('cost_center_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_a_transaction_needs_a_positive_amount(): void
    {
        $this->post('/financeiro', $this->payload(['amount' => '0']))->assertSessionHasErrors('amount');
    }

    /** Parcelamento gera um lançamento por mês, todos na mesma série. */
    public function test_installments_generate_one_entry_per_month(): void
    {
        $this->post('/financeiro', $this->payload([
            'description' => 'Notebook',
            'amount' => '1000.00',
            'due_date' => '2026-01-31',
            'installments' => 3,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('transactions', 3);

        $series = Transaction::orderBy('due_date')->get();

        $this->assertSame(['Notebook (1/3)', 'Notebook (2/3)', 'Notebook (3/3)'], $series->pluck('description')->all());
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], $series->pluck('due_date')->map->toDateString()->all());
        $this->assertCount(1, $series->pluck('series_id')->unique());
    }

    public function test_deleting_one_installment_can_take_the_whole_series(): void
    {
        $this->post('/financeiro', $this->payload(['installments' => 4]));

        $first = Transaction::orderBy('due_date')->first();

        // `whole_series` virou `scope`, que também sabe dizer "desta em diante".
        $this->delete("/financeiro/{$first->id}", ['scope' => Transaction::SCOPE_ALL])->assertSessionHas('success');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_deleting_one_installment_alone_keeps_the_others(): void
    {
        $this->post('/financeiro', $this->payload(['installments' => 4]));

        $first = Transaction::orderBy('due_date')->first();

        $this->delete("/financeiro/{$first->id}")->assertSessionHas('success');

        $this->assertDatabaseCount('transactions', 3);
    }

    public function test_the_list_can_be_filtered(): void
    {
        $category = FinanceCategory::factory()->expense()->create();
        $other = CostCenter::factory()->create(['name' => 'Casa']);
        $client = Client::factory()->create(['name' => 'Padaria do Bairro']);

        Transaction::factory()->payable()->dueIn(3)->create(['cost_center_id' => $this->center->id, 'finance_category_id' => $category->id]);
        Transaction::factory()->payable()->dueIn(3)->create(['cost_center_id' => $other->id]);
        Transaction::factory()->receivable()->dueIn(3)->create(['cost_center_id' => $this->center->id, 'client_id' => $client->id]);

        $this->get('/financeiro?type=payable')->assertInertia(fn (AssertableInertia $p) => $p->has('transactions.data', 2));
        $this->get("/financeiro?cost_center_id={$other->id}")->assertInertia(fn (AssertableInertia $p) => $p->has('transactions.data', 1));
        $this->get("/financeiro?finance_category_id={$category->id}")->assertInertia(fn (AssertableInertia $p) => $p->has('transactions.data', 1));
        $this->get('/financeiro?search=Padaria')->assertInertia(fn (AssertableInertia $p) => $p->has('transactions.data', 1));
    }

    /**
     * As bordas do mês são o ponto frágil: dia 1 e último dia precisam entrar,
     * e o dia 1 do mês seguinte precisa ficar de fora.
     */
    public function test_the_month_filter_includes_both_edges_of_the_month(): void
    {
        foreach (['2026-02-28', '2026-03-01', '2026-03-10', '2026-03-31', '2026-04-01'] as $due) {
            Transaction::factory()->payable()->create(['due_date' => $due, 'cost_center_id' => $this->center->id]);
        }

        $this->get('/financeiro?month=2026-03')->assertInertia(function (AssertableInertia $page) {
            $dates = collect($page->toArray()['props']['transactions']['data'])->pluck('due_date')->sort()->values()->all();

            $this->assertSame(['2026-03-01', '2026-03-10', '2026-03-31'], $dates);
        });
    }

    public function test_deleting_a_cost_center_keeps_the_history(): void
    {
        $transaction = Transaction::factory()->payable()->create(['cost_center_id' => $this->center->id]);

        $this->delete(route('configuracoes.centros.destroy', $this->center));

        // O lançamento sobrevive, só perde o vínculo: apagar configuração não
        // pode apagar movimento financeiro.
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'cost_center_id' => null]);
    }
}
