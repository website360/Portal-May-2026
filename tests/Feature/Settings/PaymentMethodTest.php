<?php

namespace Tests\Feature\Settings;

use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private const URL = '/configuracoes/financeiro/formas-de-pagamento';

    public function test_the_page_lists_the_registered_methods(): void
    {
        PaymentMethod::factory()->create(['name' => 'Pix']);
        PaymentMethod::factory()->create(['name' => 'Boleto']);

        $this->get(self::URL)
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('configuracoes/formas-de-pagamento')
                    ->has('paymentMethods', 2)
                    ->has('colors')
            );
    }

    public function test_a_method_can_be_created(): void
    {
        $this->post(self::URL, [
            'name' => 'Cartão de crédito',
            'description' => 'Nubank PJ',
            'color' => 'violet',
        ])->assertSessionHasNoErrors();

        $method = PaymentMethod::sole();

        $this->assertSame('Cartão de crédito', $method->name);
        $this->assertSame('Nubank PJ', $method->description);
        $this->assertTrue($method->active);
    }

    public function test_the_name_can_not_repeat(): void
    {
        PaymentMethod::factory()->create(['name' => 'Pix']);

        $this->post(self::URL, ['name' => 'Pix', 'color' => 'blue'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, PaymentMethod::count());
    }

    public function test_a_color_outside_the_palette_is_rejected(): void
    {
        $this->post(self::URL, ['name' => 'Pix', 'color' => 'fucsia'])
            ->assertSessionHasErrors('color');
    }

    public function test_a_method_can_be_renamed_and_deactivated(): void
    {
        $method = PaymentMethod::factory()->create(['name' => 'Pix']);

        $this->put(self::URL."/{$method->id}", [
            'name' => 'Pix CNPJ',
            'color' => $method->color,
            'active' => false,
        ])->assertSessionHasNoErrors();

        $method->refresh();

        $this->assertSame('Pix CNPJ', $method->name);
        $this->assertFalse($method->active);
    }

    public function test_keeping_its_own_name_on_edit_is_allowed(): void
    {
        $method = PaymentMethod::factory()->create(['name' => 'Pix']);

        $this->put(self::URL."/{$method->id}", ['name' => 'Pix', 'color' => 'blue'])
            ->assertSessionHasNoErrors();
    }

    /** Excluir a forma não pode levar o lançamento junto. */
    public function test_deleting_a_method_keeps_the_transactions_without_it(): void
    {
        $method = PaymentMethod::factory()->create();
        $transaction = Transaction::factory()->payable()->dueIn(5)->create([
            'description' => 'Hospedagem',
            'payment_method_id' => $method->id,
        ]);

        $this->delete(self::URL."/{$method->id}")->assertSessionHasNoErrors();

        $transaction->refresh();

        $this->assertSame('Hospedagem', $transaction->description);
        $this->assertNull($transaction->payment_method_id);
    }

    public function test_the_list_can_be_sorted(): void
    {
        PaymentMethod::factory()->create(['name' => 'Pix']);
        PaymentMethod::factory()->create(['name' => 'Boleto']);

        $this->get(self::URL)->assertInertia(
            fn (AssertableInertia $page) => $page->where('paymentMethods.0.name', 'Boleto')
        );

        $this->get(self::URL.'?sort=name&direction=desc')->assertInertia(
            fn (AssertableInertia $page) => $page->where('paymentMethods.0.name', 'Pix')
        );
    }
}
