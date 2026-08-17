<?php

namespace Tests\Feature\Finance;

use App\Models\Client;
use App\Models\CostCenter;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A conta de uma recorrência só nasce perto do vencimento — o que fazia o mês
 * seguinte parecer vazio, mesmo com o dinheiro já combinado. Estas linhas são
 * projeção: aparecem na listagem, não existem no banco, e somem quando a conta
 * de verdade é gerada.
 */
class ProjectedChargesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_month_filter_defaults_to_the_current_month(): void
    {
        $this->get('/financeiro')->assertInertia(
            fn (AssertableInertia $page) => $page->where('filters.month', Carbon::today()->format('Y-m'))
        );
    }

    /** Pedir explicitamente "todos os períodos" continua possível. */
    public function test_an_empty_month_still_means_every_period(): void
    {
        $this->get('/financeiro?month=')->assertInertia(
            fn (AssertableInertia $page) => $page->where('filters.month', '')->has('projected', 0)
        );
    }

    public function test_next_months_show_the_charge_that_has_not_been_generated(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'description' => 'Mensalidade',
            'amount' => 1500,
            'next_due_at' => Carbon::today()->startOfMonth()->addMonth()->toDateString(),
        ]);

        $month = Carbon::today()->addMonth()->format('Y-m');

        $this->get("/financeiro?month={$month}")->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('projected', 1)
                ->where('projected.0.description', 'Mensalidade')
                // O JSON entrega 1500 sem casas; comparar como número evita
                // encalhar em 1500 vs 1500.0.
                ->where('projected.0.amount', fn ($amount) => (float) $amount === 1500.0)
                ->where('projected.0.recurrence_id', $recurrence->id)
        );
    }

    /** Gerada a conta, a projeção some — nada aparece duas vezes. */
    public function test_a_generated_charge_replaces_its_projection(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'next_due_at' => Carbon::today()->toDateString(),
        ]);

        $month = Carbon::today()->format('Y-m');

        $this->get("/financeiro?month={$month}")->assertInertia(fn (AssertableInertia $page) => $page->has('projected', 1));

        $recurrence->generateNext();

        $this->get("/financeiro?month={$month}")->assertInertia(
            fn (AssertableInertia $page) => $page->has('projected', 0)->has('transactions.data', 1)
        );
    }

    public function test_a_stopped_recurrence_projects_nothing(): void
    {
        Recurrence::factory()->monthly()->inactive()->create([
            'next_due_at' => Carbon::today()->addMonth()->toDateString(),
        ]);

        $month = Carbon::today()->addMonth()->format('Y-m');

        $this->get("/financeiro?month={$month}")->assertInertia(fn (AssertableInertia $page) => $page->has('projected', 0));
    }

    /** Depois do fim do contrato não há mais o que projetar. */
    public function test_nothing_is_projected_past_the_end_of_the_contract(): void
    {
        Recurrence::factory()->monthly()->create([
            'next_due_at' => Carbon::today()->toDateString(),
            'ends_at' => Carbon::today()->addMonth()->toDateString(),
        ]);

        $longe = Carbon::today()->addMonths(6)->format('Y-m');

        $this->get("/financeiro?month={$longe}")->assertInertia(fn (AssertableInertia $page) => $page->has('projected', 0));
    }

    public function test_the_projection_respects_the_type_filter(): void
    {
        Recurrence::factory()->monthly()->create(['type' => Transaction::TYPE_PAYABLE, 'next_due_at' => Carbon::today()->toDateString()]);
        Recurrence::factory()->monthly()->receivable()->create(['next_due_at' => Carbon::today()->toDateString()]);

        $month = Carbon::today()->format('Y-m');

        $this->get("/financeiro?month={$month}&type=receivable")->assertInertia(
            fn (AssertableInertia $page) => $page->has('projected', 1)->where('projected.0.type', 'receivable')
        );
    }

    /** Buscar ou filtrar por situação é olhar o que existe, não o previsto. */
    public function test_searching_hides_the_projections(): void
    {
        Recurrence::factory()->monthly()->create(['description' => 'Mensalidade', 'next_due_at' => Carbon::today()->toDateString()]);

        $month = Carbon::today()->format('Y-m');

        $this->get("/financeiro?month={$month}&search=Mensalidade")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projected', 0));
    }

    public function test_the_projection_shows_the_client_brand(): void
    {
        $client = Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);

        Recurrence::factory()->monthly()->create([
            'client_id' => $client->id,
            'next_due_at' => Carbon::today()->toDateString(),
        ]);

        $month = Carbon::today()->format('Y-m');

        $this->get("/financeiro?month={$month}")->assertInertia(
            fn (AssertableInertia $page) => $page->where('projected.0.client', 'Inove-se')
        );
    }

    /** Um contrato anual não enche o mês de cobranças mensais. */
    public function test_an_annual_contract_projects_once_a_year(): void
    {
        Recurrence::factory()->annual()->create(['next_due_at' => Carbon::today()->toDateString()]);

        $esteMes = Carbon::today()->format('Y-m');
        $proximo = Carbon::today()->addMonth()->format('Y-m');

        $this->get("/financeiro?month={$esteMes}")->assertInertia(fn (AssertableInertia $page) => $page->has('projected', 1));
        $this->get("/financeiro?month={$proximo}")->assertInertia(fn (AssertableInertia $page) => $page->has('projected', 0));
    }

    public function test_transactions_can_be_sorted_by_client(): void
    {
        $center = CostCenter::factory()->create();
        $alfa = Client::factory()->create(['name' => 'Zeta Ltda', 'trade_name' => 'Alfa Marcas']);
        $zeta = Client::factory()->create(['name' => 'Alfa Ltda', 'trade_name' => 'Zeta Marcas']);

        Transaction::factory()->payable()->create(['description' => 'Do Zeta', 'client_id' => $zeta->id, 'due_date' => Carbon::today(), 'cost_center_id' => $center->id]);
        Transaction::factory()->payable()->create(['description' => 'Do Alfa', 'client_id' => $alfa->id, 'due_date' => Carbon::today(), 'cost_center_id' => $center->id]);

        $month = Carbon::today()->format('Y-m');

        $this->get("/financeiro?month={$month}&sort=client")->assertInertia(
            fn (AssertableInertia $page) => $page->where('transactions.data.0.description', 'Do Alfa')
        );
    }
}
