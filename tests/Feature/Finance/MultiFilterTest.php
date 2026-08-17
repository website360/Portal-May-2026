<?php

namespace Tests\Feature\Finance;

use App\Models\CostCenter;
use App\Models\FinanceCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Os filtros aceitam mais de um valor — "em aberto" e "vencida" juntas.
 *
 * O detalhe que exige cuidado: situações combinadas precisam de OR. Com AND o
 * resultado seria sempre vazio, já que nenhuma conta é paga e vencida ao mesmo
 * tempo.
 */
class MultiFilterTest extends TestCase
{
    use RefreshDatabase;

    private CostCenter $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->center = CostCenter::factory()->create();
    }

    private function conta(string $description, string $type, ?string $paidAt, string $dueDate): Transaction
    {
        return Transaction::factory()->create([
            'description' => $description,
            'type' => $type,
            'due_date' => $dueDate,
            'paid_at' => $paidAt,
            'paid_amount' => $paidAt ? 100 : null,
            'cost_center_id' => $this->center->id,
        ]);
    }

    private function descricoes(string $url): array
    {
        $names = [];

        $this->get($url)->assertInertia(function (AssertableInertia $page) use (&$names) {
            $names = collect($page->toArray()['props']['transactions']['data'])->pluck('description')->sort()->values()->all();
        });

        return $names;
    }

    public function test_two_statuses_come_together(): void
    {
        $hoje = Carbon::today();

        $this->conta('Vencida', Transaction::TYPE_PAYABLE, null, $hoje->copy()->subDays(3)->toDateString());
        $this->conta('A vencer', Transaction::TYPE_PAYABLE, null, $hoje->copy()->addDays(3)->toDateString());
        $this->conta('Baixada', Transaction::TYPE_PAYABLE, $hoje->toDateString(), $hoje->toDateString());

        $mes = $hoje->format('Y-m');

        // Vencida + baixada: as duas, e só elas.
        $this->assertSame(['Baixada', 'Vencida'], $this->descricoes("/financeiro?month={$mes}&status[]=overdue&status[]=paid"));
    }

    /** Com AND o resultado seria vazio: nenhuma conta é paga e vencida ao mesmo tempo. */
    public function test_combining_statuses_does_not_return_empty(): void
    {
        $hoje = Carbon::today();

        $this->conta('Vencida', Transaction::TYPE_PAYABLE, null, $hoje->copy()->subDay()->toDateString());

        $mes = $hoje->format('Y-m');

        $this->assertSame(['Vencida'], $this->descricoes("/financeiro?month={$mes}&status[]=overdue&status[]=paid"));
    }

    public function test_both_types_together_is_the_same_as_no_filter(): void
    {
        $hoje = Carbon::today()->toDateString();

        $this->conta('Saída', Transaction::TYPE_PAYABLE, null, $hoje);
        $this->conta('Entrada', Transaction::TYPE_RECEIVABLE, null, $hoje);

        $mes = Carbon::today()->format('Y-m');

        $this->assertSame(['Entrada', 'Saída'], $this->descricoes("/financeiro?month={$mes}&type[]=payable&type[]=receivable"));
    }

    public function test_several_cost_centers_at_once(): void
    {
        $outro = CostCenter::factory()->create();
        $terceiro = CostCenter::factory()->create();
        $hoje = Carbon::today()->toDateString();

        $this->conta('Do primeiro', Transaction::TYPE_PAYABLE, null, $hoje);
        Transaction::factory()->create(['description' => 'Do segundo', 'due_date' => $hoje, 'cost_center_id' => $outro->id, 'paid_at' => null]);
        Transaction::factory()->create(['description' => 'Do terceiro', 'due_date' => $hoje, 'cost_center_id' => $terceiro->id, 'paid_at' => null]);

        $mes = Carbon::today()->format('Y-m');
        $url = "/financeiro?month={$mes}&cost_center_id[]={$this->center->id}&cost_center_id[]={$outro->id}";

        $this->assertSame(['Do primeiro', 'Do segundo'], $this->descricoes($url));
    }

    public function test_several_categories_at_once(): void
    {
        $uma = FinanceCategory::factory()->expense()->create();
        $outra = FinanceCategory::factory()->expense()->create();
        $hoje = Carbon::today()->toDateString();

        Transaction::factory()->create(['description' => 'Com uma', 'due_date' => $hoje, 'cost_center_id' => $this->center->id, 'finance_category_id' => $uma->id, 'paid_at' => null]);
        Transaction::factory()->create(['description' => 'Com outra', 'due_date' => $hoje, 'cost_center_id' => $this->center->id, 'finance_category_id' => $outra->id, 'paid_at' => null]);
        Transaction::factory()->create(['description' => 'Sem categoria', 'due_date' => $hoje, 'cost_center_id' => $this->center->id, 'paid_at' => null]);

        $mes = Carbon::today()->format('Y-m');
        $url = "/financeiro?month={$mes}&finance_category_id[]={$uma->id}&finance_category_id[]={$outra->id}";

        $this->assertSame(['Com outra', 'Com uma'], $this->descricoes($url));
    }

    /** Filtros diferentes se cruzam com E, mesmo aceitando lista cada um. */
    public function test_different_filters_still_narrow_each_other(): void
    {
        $hoje = Carbon::today();

        $this->conta('Saída vencida', Transaction::TYPE_PAYABLE, null, $hoje->copy()->subDay()->toDateString());
        $this->conta('Entrada vencida', Transaction::TYPE_RECEIVABLE, null, $hoje->copy()->subDay()->toDateString());

        $mes = $hoje->format('Y-m');

        $this->assertSame(['Saída vencida'], $this->descricoes("/financeiro?month={$mes}&type[]=payable&status[]=overdue&status[]=paid"));
    }

    /** Link antigo com valor único continua funcionando. */
    public function test_a_single_value_in_the_url_still_works(): void
    {
        $hoje = Carbon::today()->toDateString();

        $this->conta('Saída', Transaction::TYPE_PAYABLE, null, $hoje);
        $this->conta('Entrada', Transaction::TYPE_RECEIVABLE, null, $hoje);

        $mes = Carbon::today()->format('Y-m');

        $this->assertSame(['Saída'], $this->descricoes("/financeiro?month={$mes}&type=payable"));
    }

    public function test_the_filters_come_back_to_the_page_as_lists(): void
    {
        $mes = Carbon::today()->format('Y-m');

        $this->get("/financeiro?month={$mes}&status[]=open&status[]=overdue")->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('filters.status', ['open', 'overdue'])
                ->where('filters.type', [])
        );
    }

    public function test_an_invalid_status_is_ignored_instead_of_emptying_the_list(): void
    {
        $hoje = Carbon::today()->toDateString();

        $this->conta('Existe', Transaction::TYPE_PAYABLE, null, $hoje);

        $mes = Carbon::today()->format('Y-m');

        $this->assertSame(['Existe'], $this->descricoes("/financeiro?month={$mes}&status[]=inventado"));
    }
}
