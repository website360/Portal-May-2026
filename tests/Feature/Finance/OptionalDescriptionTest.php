<?php

namespace Tests\Feature\Finance;

use App\Models\CostCenter;
use App\Models\FinanceCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A descrição deixou de ser obrigatória. Como a listagem inteira se orienta por
 * esse texto, quem não digita nada recebe um rótulo derivado — em vez de uma
 * linha muda no meio do extrato.
 */
class OptionalDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private CostCenter $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->center = CostCenter::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => Transaction::TYPE_PAYABLE,
            'amount' => '150.00',
            'due_date' => now()->toDateString(),
            'cost_center_id' => $this->center->id,
        ], $overrides);
    }

    public function test_a_transaction_can_be_created_without_a_description(): void
    {
        $this->post('/financeiro', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
    }

    public function test_an_empty_description_is_rejected_no_more(): void
    {
        $this->post('/financeiro', $this->payload(['description' => '']))
            ->assertSessionHasNoErrors();
    }

    /**
     * A categoria de propósito NÃO vira descrição.
     *
     * Ela tem coluna própria na listagem: usá-la como rótulo faria a mesma
     * palavra aparecer duas vezes na linha, sem acrescentar nada.
     */
    public function test_the_category_does_not_become_the_description(): void
    {
        $category = FinanceCategory::factory()->expense()->create(['name' => 'Aluguel']);

        $this->post('/financeiro', $this->payload(['finance_category_id' => $category->id]));

        $this->assertNotSame('Aluguel', Transaction::sole()->description);
    }

    /** O fornecedor serve de rótulo — ele não tem coluna própria. */
    public function test_the_counterpart_names_it(): void
    {
        $this->post('/financeiro', $this->payload(['counterpart' => 'Locaweb']));

        $this->assertSame('Locaweb', Transaction::sole()->description);
    }

    /** Com categoria E fornecedor, o fornecedor ganha: a categoria já está visível. */
    public function test_the_counterpart_wins_over_the_category(): void
    {
        $category = FinanceCategory::factory()->expense()->create(['name' => 'Aluguel']);

        $this->post('/financeiro', $this->payload([
            'finance_category_id' => $category->id,
            'counterpart' => 'Imobiliária Central',
        ]));

        $this->assertSame('Imobiliária Central', Transaction::sole()->description);
    }

    public function test_without_anything_it_falls_back_to_the_direction(): void
    {
        $this->post('/financeiro', $this->payload());
        $this->assertSame('Pagamento', Transaction::sole()->description);

        Transaction::query()->delete();

        $this->post('/financeiro', $this->payload(['type' => Transaction::TYPE_RECEIVABLE]));
        $this->assertSame('Recebimento', Transaction::sole()->description);
    }

    /** O que foi digitado sempre ganha do derivado. */
    public function test_a_typed_description_always_wins(): void
    {
        $category = FinanceCategory::factory()->expense()->create(['name' => 'Aluguel']);

        $this->post('/financeiro', $this->payload([
            'description' => 'Aluguel de novembro',
            'finance_category_id' => $category->id,
            'counterpart' => 'Imobiliária',
        ]));

        $this->assertSame('Aluguel de novembro', Transaction::sole()->description);
    }

    /** Só espaços é o mesmo que vazio. */
    public function test_whitespace_alone_counts_as_empty(): void
    {
        $this->post('/financeiro', $this->payload(['description' => '   ', 'counterpart' => 'Locaweb']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Locaweb', Transaction::sole()->description);
    }

    public function test_editing_without_a_description_keeps_a_label(): void
    {
        $transaction = Transaction::factory()->payable()->dueIn(3)->create(['description' => 'Antigo']);

        $this->put("/financeiro/{$transaction->id}", $this->payload(['description' => '', 'counterpart' => 'Locaweb']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Locaweb', $transaction->refresh()->description);
    }

    /** Parcelas continuam numeradas mesmo com a descrição derivada. */
    public function test_installments_number_the_derived_description(): void
    {
        $this->post('/financeiro', $this->payload(['counterpart' => 'Locaweb', 'installments' => 3]));

        $this->assertSame('Locaweb (1/3)', Transaction::orderBy('due_date')->first()->description);
    }
}
