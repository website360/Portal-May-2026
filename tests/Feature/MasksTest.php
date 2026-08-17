<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CostCenter;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As máscaras moram no front, mas o que elas produzem tem que atravessar o
 * servidor sem perder nada. Estes testes prendem o formato que chega e o que
 * volta — é onde um "1.234,56" viraria 1,00 em silêncio.
 */
class MasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_a_masked_document_and_phone_are_stored_as_typed(): void
    {
        $this->post('/clientes', [
            'type' => Client::TYPE_COMPANY,
            'name' => 'Padaria Ltda',
            'status' => Client::STATUS_ACTIVE,
            'document' => '12.345.678/0001-90',
            'phone' => '(11) 98888-7777',
            'zip_code' => '01310-100',
        ])->assertSessionHasNoErrors();

        $client = Client::sole();

        $this->assertSame('12.345.678/0001-90', $client->document);
        $this->assertSame('(11) 98888-7777', $client->phone);
        $this->assertSame('01310-100', $client->zip_code);
    }

    /** O CurrencyInput manda número puro; a máscara não pode vazar. */
    public function test_money_arrives_as_a_plain_decimal(): void
    {
        $this->post('/clientes', [
            'type' => Client::TYPE_COMPANY,
            'name' => 'Padaria Ltda',
            'status' => Client::STATUS_ACTIVE,
            'monthly_fee' => '1234.56',
        ])->assertSessionHasNoErrors();

        $this->assertSame('1234.56', (string) Client::sole()->monthly_fee);
    }

    public function test_cents_survive_the_round_trip(): void
    {
        // 0,07 é o caso que denuncia arredondamento errado no caminho.
        $center = CostCenter::factory()->create();

        $this->post('/financeiro', [
            'type' => 'payable',
            'description' => 'Tarifa bancária',
            'amount' => '0.07',
            'due_date' => now()->toDateString(),
            'cost_center_id' => $center->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('0.07', (string) Transaction::sole()->amount);
    }

    public function test_a_large_amount_keeps_every_digit(): void
    {
        $center = CostCenter::factory()->create();

        $this->post('/financeiro', [
            'type' => 'receivable',
            'description' => 'Projeto anual',
            'amount' => '1987654.32',
            'due_date' => now()->toDateString(),
            'cost_center_id' => $center->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('1987654.32', (string) Transaction::sole()->amount);
    }

    /** O que a listagem devolve é o que o CurrencyInput vai remascarar. */
    public function test_the_listing_returns_money_as_a_number_not_a_masked_string(): void
    {
        Client::factory()->create(['monthly_fee' => 2500.5]);

        $this->get('/clientes')->assertInertia(
            fn ($page) => $page->where('clients.data.0.monthly_fee', 2500.5)
        );
    }
}
