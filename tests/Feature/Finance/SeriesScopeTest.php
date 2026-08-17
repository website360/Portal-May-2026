<?php

namespace Tests\Feature\Finance;

use App\Models\CostCenter;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Editar ou excluir uma conta de série pergunta o alcance: só esta, desta em
 * diante, ou todas.
 *
 * O padrão é sempre o mais conservador — só esta. Errar para menos se conserta
 * repetindo a ação; errar para mais apaga o que ninguém mandou apagar.
 */
class SeriesScopeTest extends TestCase
{
    use RefreshDatabase;

    private CostCenter $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->center = CostCenter::factory()->create();
    }

    /** @return array<int, Transaction> */
    private function parcelas(int $quantas = 4): array
    {
        $this->post('/financeiro', [
            'type' => Transaction::TYPE_PAYABLE,
            'description' => 'Notebook',
            'amount' => '100.00',
            'due_date' => '2026-08-10',
            'cost_center_id' => $this->center->id,
            'repeat' => 'installments',
            'installments' => $quantas,
        ]);

        return Transaction::orderBy('due_date')->get()->all();
    }

    public function test_deleting_only_this_one_leaves_the_rest(): void
    {
        [$primeira] = $this->parcelas();

        $this->delete("/financeiro/{$primeira->id}", ['scope' => Transaction::SCOPE_ONE]);

        $this->assertSame(3, Transaction::count());
    }

    /** Sem escopo declarado, apaga só esta — nunca a série toda por engano. */
    public function test_the_default_scope_is_the_safest_one(): void
    {
        [$primeira] = $this->parcelas();

        $this->delete("/financeiro/{$primeira->id}");

        $this->assertSame(3, Transaction::count());
    }

    public function test_deleting_from_here_forward_keeps_the_past(): void
    {
        $parcelas = $this->parcelas();

        // Da terceira em diante: sobram as duas primeiras.
        $this->delete("/financeiro/{$parcelas[2]->id}", ['scope' => Transaction::SCOPE_FORWARD]);

        $this->assertSame(2, Transaction::count());
        $this->assertSame('2026-09-10', Transaction::orderByDesc('due_date')->first()->due_date->toDateString());
    }

    public function test_deleting_all_removes_the_whole_series(): void
    {
        $parcelas = $this->parcelas();

        $this->delete("/financeiro/{$parcelas[1]->id}", ['scope' => Transaction::SCOPE_ALL]);

        $this->assertSame(0, Transaction::count());
    }

    /** Conta avulsa não tem série: qualquer escopo alcança só ela. */
    public function test_a_single_transaction_is_never_dragged_by_scope(): void
    {
        Transaction::factory()->payable()->dueIn(3)->count(2)->create(['cost_center_id' => $this->center->id]);

        $alvo = Transaction::first();

        $this->delete("/financeiro/{$alvo->id}", ['scope' => Transaction::SCOPE_ALL]);

        $this->assertSame(1, Transaction::count());
    }

    public function test_editing_all_propagates_the_shared_fields(): void
    {
        $parcelas = $this->parcelas();

        $this->put("/financeiro/{$parcelas[0]->id}", [
            'type' => Transaction::TYPE_PAYABLE,
            'description' => 'Notebook novo',
            'amount' => '250.00',
            'due_date' => $parcelas[0]->due_date->toDateString(),
            'cost_center_id' => $this->center->id,
            'scope' => Transaction::SCOPE_ALL,
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, Transaction::where('amount', '250.00')->count());
        $this->assertSame(4, Transaction::where('description', 'Notebook novo')->count());
    }

    /**
     * Vencimento não se propaga: propagar colocaria as quatro parcelas no mesmo
     * dia, desmontando o parcelamento.
     */
    public function test_editing_all_does_not_move_the_other_due_dates(): void
    {
        $parcelas = $this->parcelas();
        $datasAntes = Transaction::orderBy('due_date')->pluck('due_date')->map(fn ($d) => $d->toDateString())->all();

        $this->put("/financeiro/{$parcelas[0]->id}", [
            'type' => Transaction::TYPE_PAYABLE,
            'description' => 'Notebook',
            'amount' => '250.00',
            'due_date' => '2026-08-20',
            'cost_center_id' => $this->center->id,
            'scope' => Transaction::SCOPE_ALL,
        ]);

        $datasDepois = Transaction::orderBy('due_date')->pluck('due_date')->map(fn ($d) => $d->toDateString())->all();

        // Só a editada mudou de data.
        $this->assertSame('2026-08-20', Transaction::find($parcelas[0]->id)->due_date->toDateString());
        $this->assertSame(array_slice($datasAntes, 1), array_slice($datasDepois, 1));
    }

    /** Baixa também não se propaga: daria por paga conta que ninguém pagou. */
    public function test_editing_all_does_not_settle_the_others(): void
    {
        $parcelas = $this->parcelas();

        $this->put("/financeiro/{$parcelas[0]->id}", [
            'type' => Transaction::TYPE_PAYABLE,
            'description' => 'Notebook',
            'amount' => '100.00',
            'due_date' => $parcelas[0]->due_date->toDateString(),
            'cost_center_id' => $this->center->id,
            'paid_at' => '2026-08-11',
            'paid_amount' => '100.00',
            'scope' => Transaction::SCOPE_ALL,
        ]);

        $this->assertSame(1, Transaction::whereNotNull('paid_at')->count());
    }

    public function test_editing_forward_leaves_the_earlier_ones_alone(): void
    {
        $parcelas = $this->parcelas();

        $this->put("/financeiro/{$parcelas[2]->id}", [
            'type' => Transaction::TYPE_PAYABLE,
            'description' => 'Notebook',
            'amount' => '300.00',
            'due_date' => $parcelas[2]->due_date->toDateString(),
            'cost_center_id' => $this->center->id,
            'scope' => Transaction::SCOPE_FORWARD,
        ]);

        $valores = Transaction::orderBy('due_date')->pluck('amount')->map(fn ($v) => (string) $v)->all();

        $this->assertSame(['100.00', '100.00', '300.00', '300.00'], $valores);
    }

    /** O mesmo vale para cobrança de contrato, que agrupa por recorrência. */
    public function test_it_works_for_recurring_charges_too(): void
    {
        $this->post('/financeiro', [
            'type' => Transaction::TYPE_RECEIVABLE,
            'description' => 'Mensalidade',
            'amount' => '500.00',
            'due_date' => '2026-08-10',
            'cost_center_id' => $this->center->id,
            'repeat' => 'recurring',
            'interval' => Recurrence::MONTHLY,
            'occurrences' => 6,
        ]);

        $this->assertSame(6, Transaction::count());

        $terceira = Transaction::orderBy('due_date')->skip(2)->first();

        $this->delete("/financeiro/{$terceira->id}", ['scope' => Transaction::SCOPE_FORWARD]);

        $this->assertSame(2, Transaction::count());
    }

    public function test_an_invented_scope_falls_back_to_this_one_only(): void
    {
        [$primeira] = $this->parcelas();

        $this->delete("/financeiro/{$primeira->id}", ['scope' => 'tudo-mesmo']);

        $this->assertSame(3, Transaction::count());
    }
}
