<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CostCenter;
use App\Models\Recurrence;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * A agência conhece o cliente pela marca, não pela razão social nem pelo nome
 * civil: ninguém reconhece "Adriana Maria dos Santos Veigas", mas todo mundo
 * sabe quem é "Inove-se".
 */
class ClientDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_brand_wins_over_the_legal_name(): void
    {
        $client = Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);

        $this->assertSame('Inove-se', $client->display_name);
    }

    public function test_without_a_brand_the_name_is_used(): void
    {
        $client = Client::factory()->create(['name' => 'Padaria do Bairro Ltda', 'trade_name' => null]);

        $this->assertSame('Padaria do Bairro Ltda', $client->display_name);
    }

    public function test_an_empty_brand_counts_as_none(): void
    {
        $client = Client::factory()->create(['name' => 'Padaria do Bairro Ltda', 'trade_name' => '']);

        $this->assertSame('Padaria do Bairro Ltda', $client->display_name);
    }

    /** O seletor de cliente ordena e rotula pela marca. */
    public function test_the_picker_lists_and_sorts_by_brand(): void
    {
        Client::factory()->create(['name' => 'Zeta Ltda', 'trade_name' => 'Alfa Marcas']);
        Client::factory()->create(['name' => 'Alfa Ltda', 'trade_name' => 'Zeta Marcas']);

        $names = Client::pickList()->pluck('name')->all();

        $this->assertSame(['Alfa Marcas', 'Zeta Marcas'], $names);
    }

    public function test_the_finance_picker_shows_the_brand(): void
    {
        Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);

        $this->get('/financeiro')->assertInertia(
            fn (AssertableInertia $page) => $page->where('clients.0.name', 'Inove-se')
        );
    }

    public function test_the_task_picker_shows_the_brand(): void
    {
        Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);

        $this->get('/tarefas')->assertInertia(
            fn (AssertableInertia $page) => $page->where('clients.0.name', 'Inove-se')
        );
    }

    public function test_the_domain_picker_shows_the_brand(): void
    {
        Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);

        $this->get('/dominios')->assertInertia(
            fn (AssertableInertia $page) => $page->where('clients.0.name', 'Inove-se')
        );
    }

    /** Na listagem do financeiro, a linha também mostra a marca. */
    public function test_the_transaction_row_shows_the_brand(): void
    {
        $client = Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);
        Transaction::factory()->payable()->dueIn(3)->create(['client_id' => $client->id]);

        $this->get('/financeiro')->assertInertia(
            fn (AssertableInertia $page) => $page->where('transactions.data.0.client.name', 'Inove-se')
        );
    }

    public function test_the_task_row_shows_the_brand(): void
    {
        $client = Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);
        Task::factory()->open()->dueIn(3)->create(['client_id' => $client->id]);

        $this->get('/tarefas')->assertInertia(
            fn (AssertableInertia $page) => $page->where('tasks.data.0.client.name', 'Inove-se')
        );
    }

    public function test_the_recurrence_row_shows_the_brand(): void
    {
        $client = Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);
        Recurrence::factory()->create(['client_id' => $client->id]);

        $this->get('/financeiro/recorrencias')->assertInertia(
            fn (AssertableInertia $page) => $page->where('recurrences.0.client.name', 'Inove-se')
        );
    }

    /** O aviso de contrato acabando também. */
    public function test_the_dashboard_warning_shows_the_brand(): void
    {
        $client = Client::factory()->create(['name' => 'Adriana Maria dos Santos Veigas', 'trade_name' => 'Inove-se']);

        Recurrence::factory()->monthly()->create([
            'client_id' => $client->id,
            'next_due_at' => now()->addDays(5)->toDateString(),
            'ends_at' => now()->addDays(20)->toDateString(),
        ]);

        $this->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page->where('endingRecurrences.0.client', 'Inove-se')
        );
    }

    /** O filtro de mês precisa olhar para a frente, não só para o que já existe. */
    public function test_the_month_filter_offers_the_year_ahead(): void
    {
        CostCenter::factory()->create();

        $this->get('/financeiro')->assertInertia(function (AssertableInertia $page) {
            $months = $page->toArray()['props']['months'];

            $this->assertContains(now()->format('Y-m'), $months);
            $this->assertContains(now()->addMonths(6)->format('Y-m'), $months);
            $this->assertContains(now()->addMonths(12)->format('Y-m'), $months);
        });
    }

    /** Meses passados com lançamento continuam disponíveis. */
    public function test_past_months_with_transactions_stay_available(): void
    {
        Transaction::factory()->payable()->create(['due_date' => now()->subMonths(3)->toDateString()]);

        $this->get('/financeiro')->assertInertia(function (AssertableInertia $page) {
            $this->assertContains(now()->subMonths(3)->format('Y-m'), $page->toArray()['props']['months']);
        });
    }
}
