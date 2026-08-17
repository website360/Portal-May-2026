<?php

namespace Tests\Feature\Settings;

use App\Models\CostCenter;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/configuracoes/financeiro/fornecedores';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_lists_the_suppliers(): void
    {
        Supplier::factory()->create(['name' => 'Locaweb Serviços de Internet S.A.']);

        $this->get(self::URL)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('configuracoes/fornecedores')->has('suppliers', 1));
    }

    public function test_a_supplier_can_be_created(): void
    {
        $this->post(self::URL, [
            'name' => 'Locaweb Serviços de Internet S.A.',
            'trade_name' => 'Locaweb',
            'document' => '02.351.877/0001-52',
        ])->assertSessionHasNoErrors();

        $supplier = Supplier::sole();

        $this->assertSame('Locaweb', $supplier->trade_name);
        $this->assertTrue($supplier->active);
    }

    public function test_the_name_can_not_repeat(): void
    {
        Supplier::factory()->create(['name' => 'Locaweb']);

        $this->post(self::URL, ['name' => 'Locaweb'])->assertSessionHasErrors('name');

        $this->assertSame(1, Supplier::count());
    }

    /** A marca vem antes da razão social, como nos clientes. */
    public function test_the_brand_is_what_shows_up(): void
    {
        $comMarca = Supplier::factory()->create(['name' => 'Locaweb Serviços S.A.', 'trade_name' => 'Locaweb']);
        $semMarca = Supplier::factory()->create(['name' => 'Papelaria Central', 'trade_name' => null]);

        $this->assertSame('Locaweb', $comMarca->display_name);
        $this->assertSame('Papelaria Central', $semMarca->display_name);
    }

    /** Quem digita a razão social ou o CNPJ também encontra. */
    public function test_the_picker_carries_hidden_search_terms(): void
    {
        Supplier::factory()->create([
            'name' => 'Locaweb Serviços de Internet S.A.',
            'trade_name' => 'Locaweb',
            'document' => '02.351.877/0001-52',
        ]);

        $option = Supplier::pickList()->first();

        $this->assertSame('Locaweb', $option['name']);
        $this->assertStringContainsString('Serviços de Internet', $option['search']);
        $this->assertStringContainsString('02.351.877', $option['search']);
    }

    public function test_an_inactive_supplier_is_not_offered(): void
    {
        Supplier::factory()->create(['name' => 'Ativo']);
        Supplier::factory()->inactive()->create(['name' => 'Antigo']);

        $this->assertSame(['Ativo'], Supplier::pickList()->pluck('name')->all());
    }

    public function test_a_transaction_can_point_to_a_supplier(): void
    {
        $center = CostCenter::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Locaweb Serviços S.A.', 'trade_name' => 'Locaweb']);

        $this->post('/financeiro', [
            'type' => Transaction::TYPE_PAYABLE,
            'amount' => '120.00',
            'due_date' => now()->toDateString(),
            'cost_center_id' => $center->id,
            'supplier_id' => $supplier->id,
        ])->assertSessionHasNoErrors();

        $transaction = Transaction::sole();

        $this->assertSame($supplier->id, $transaction->supplier_id);
        // Sem descrição digitada, o fornecedor vira o rótulo.
        $this->assertSame('Locaweb', $transaction->description);
    }

    /** Excluir o fornecedor não pode levar o lançamento junto. */
    public function test_deleting_a_supplier_keeps_the_transactions(): void
    {
        $supplier = Supplier::factory()->create();
        $transaction = Transaction::factory()->payable()->dueIn(3)->create([
            'description' => 'Hospedagem',
            'supplier_id' => $supplier->id,
        ]);

        $this->delete(self::URL."/{$supplier->id}")->assertSessionHasNoErrors();

        $this->assertSame('Hospedagem', $transaction->refresh()->description);
        $this->assertNull($transaction->refresh()->supplier_id);
    }

    public function test_a_supplier_that_does_not_exist_is_rejected(): void
    {
        $center = CostCenter::factory()->create();

        $this->post('/financeiro', [
            'type' => Transaction::TYPE_PAYABLE,
            'amount' => '120.00',
            'due_date' => now()->toDateString(),
            'cost_center_id' => $center->id,
            'supplier_id' => 9999,
        ])->assertSessionHasErrors('supplier_id');
    }
}
