<?php

namespace Tests\Feature\Finance;

use App\Models\CostCenter;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TransactionPaymentMethodTest extends TestCase
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
            'description' => 'Hospedagem',
            'amount' => '120.00',
            'due_date' => now()->toDateString(),
            'cost_center_id' => $this->center->id,
        ], $overrides);
    }

    public function test_the_form_receives_the_registered_methods(): void
    {
        PaymentMethod::factory()->create(['name' => 'Pix']);
        PaymentMethod::factory()->create(['name' => 'Boleto']);

        $this->get('/financeiro')->assertInertia(
            fn (AssertableInertia $page) => $page->has('paymentMethods', 2)->where('paymentMethods.0.name', 'Boleto')
        );
    }

    /** Forma inativa não some do histórico, mas não aparece para escolher. */
    public function test_an_inactive_method_is_not_offered(): void
    {
        PaymentMethod::factory()->create(['name' => 'Pix']);
        PaymentMethod::factory()->inactive()->create(['name' => 'Cheque']);

        $this->get('/financeiro')->assertInertia(
            fn (AssertableInertia $page) => $page->has('paymentMethods', 1)->where('paymentMethods.0.name', 'Pix')
        );
    }

    public function test_a_transaction_is_created_with_a_payment_method(): void
    {
        $pix = PaymentMethod::factory()->create(['name' => 'Pix']);

        $this->post('/financeiro', $this->payload(['payment_method_id' => $pix->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($pix->id, Transaction::sole()->payment_method_id);
    }

    public function test_the_listing_shows_the_method_name(): void
    {
        $pix = PaymentMethod::factory()->create(['name' => 'Pix']);
        Transaction::factory()->payable()->dueIn(3)->create(['payment_method_id' => $pix->id]);

        $this->get('/financeiro')->assertInertia(
            fn (AssertableInertia $page) => $page->where('transactions.data.0.payment_method', 'Pix')
        );
    }

    /**
     * O que foi digitado antes do cadastro existir continua legível: o texto
     * antigo vira o rótulo quando não há forma vinculada.
     */
    public function test_the_old_free_text_still_shows_when_there_is_no_linked_method(): void
    {
        Transaction::factory()->payable()->dueIn(3)->create([
            'payment_method' => 'Cartão da Ana',
            'payment_method_id' => null,
        ]);

        $this->get('/financeiro')->assertInertia(
            fn (AssertableInertia $page) => $page->where('transactions.data.0.payment_method', 'Cartão da Ana')
        );
    }

    public function test_a_method_that_does_not_exist_is_rejected(): void
    {
        $this->post('/financeiro', $this->payload(['payment_method_id' => 9999]))
            ->assertSessionHasErrors('payment_method_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_the_method_is_optional(): void
    {
        $this->post('/financeiro', $this->payload())->assertSessionHasNoErrors();

        $this->assertNull(Transaction::sole()->payment_method_id);
    }

    public function test_the_method_can_be_changed_and_cleared(): void
    {
        $pix = PaymentMethod::factory()->create(['name' => 'Pix']);
        $boleto = PaymentMethod::factory()->create(['name' => 'Boleto']);

        $transaction = Transaction::factory()->payable()->dueIn(3)->create(['payment_method_id' => $pix->id]);

        $this->put("/financeiro/{$transaction->id}", $this->payload(['payment_method_id' => $boleto->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame($boleto->id, $transaction->refresh()->payment_method_id);

        $this->put("/financeiro/{$transaction->id}", $this->payload(['payment_method_id' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($transaction->refresh()->payment_method_id);
    }

    /** Todas as parcelas nascem com a mesma forma. */
    public function test_installments_inherit_the_method(): void
    {
        $pix = PaymentMethod::factory()->create(['name' => 'Pix']);

        $this->post('/financeiro', $this->payload(['payment_method_id' => $pix->id, 'installments' => 3]))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, Transaction::count());
        $this->assertSame(3, Transaction::where('payment_method_id', $pix->id)->count());
    }
}
