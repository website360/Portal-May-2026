<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\CostCenter;
use App\Models\Domain;
use App\Models\FinanceCategory;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ListSorting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Toda listagem ordena pela URL. O ponto sensível é que a chave de ordenação
 * vem do cliente: nada fora do mapa declarado no controller pode virar SQL.
 */
class ListSortingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * @param  array<int, mixed>  $expected
     */
    private function assertOrder(string $url, string $path, array $expected): void
    {
        $this->get($url)->assertInertia(function (AssertableInertia $page) use ($path, $expected) {
            $rows = data_get($page->toArray()['props'], $path);

            $this->assertSame($expected, $rows);
        });
    }

    public function test_clients_sort_by_name_and_fee(): void
    {
        Client::factory()->create(['name' => 'Beta', 'monthly_fee' => 100]);
        Client::factory()->create(['name' => 'Alfa', 'monthly_fee' => 900]);
        Client::factory()->create(['name' => 'Gama', 'monthly_fee' => 500]);

        $this->assertOrder('/clientes', 'clients.data.*.name', ['Alfa', 'Beta', 'Gama']);
        $this->assertOrder('/clientes?sort=name&direction=desc', 'clients.data.*.name', ['Gama', 'Beta', 'Alfa']);
        $this->assertOrder('/clientes?sort=monthly_fee&direction=desc', 'clients.data.*.name', ['Alfa', 'Gama', 'Beta']);
    }

    /** Cliente sem cidade é ausência de dado, não a primeira cidade do alfabeto. */
    public function test_clients_without_a_city_stay_last_in_both_directions(): void
    {
        Client::factory()->create(['name' => 'Tem cidade', 'city' => 'Santos']);
        Client::factory()->create(['name' => 'Sem cidade', 'city' => null]);
        Client::factory()->create(['name' => 'Cidade vazia', 'city' => '']);

        $this->assertOrder('/clientes?sort=city', 'clients.data.*.name', ['Tem cidade', 'Sem cidade', 'Cidade vazia']);

        // Na descendente os vazios continuam no fim, só os preenchidos invertem.
        $this->get('/clientes?sort=city&direction=desc')->assertInertia(
            fn (AssertableInertia $page) => $page->where('clients.data.0.name', 'Tem cidade')
        );
    }

    public function test_clients_without_a_fee_stay_last_when_sorting_by_fee(): void
    {
        Client::factory()->create(['name' => 'Paga', 'monthly_fee' => 500]);
        Client::factory()->create(['name' => 'Sem mensalidade', 'monthly_fee' => null]);

        $this->assertOrder('/clientes?sort=monthly_fee', 'clients.data.*.name', ['Paga', 'Sem mensalidade']);
        $this->assertOrder('/clientes?sort=monthly_fee&direction=desc', 'clients.data.*.name', ['Paga', 'Sem mensalidade']);
    }

    public function test_domains_sort_by_client_name_through_the_relation(): void
    {
        $beta = Client::factory()->create(['name' => 'Beta']);
        $alfa = Client::factory()->create(['name' => 'Alfa']);

        Domain::factory()->for($beta)->create(['name' => 'beta.com.br']);
        Domain::factory()->for($alfa)->create(['name' => 'alfa.com.br']);

        $this->assertOrder('/dominios?sort=client', 'domains.data.*.name', ['alfa.com.br', 'beta.com.br']);
        $this->assertOrder('/dominios?sort=client&direction=desc', 'domains.data.*.name', ['beta.com.br', 'alfa.com.br']);
    }

    /** Sem data vai para o fim, ordene como ordenar. */
    public function test_domains_without_expiration_stay_last_in_both_directions(): void
    {
        $client = Client::factory()->create();

        Domain::factory()->for($client)->create(['name' => 'cedo.com.br', 'expires_at' => now()->addDays(5)]);
        Domain::factory()->for($client)->create(['name' => 'tarde.com.br', 'expires_at' => now()->addDays(500)]);
        Domain::factory()->for($client)->withoutExpiration()->create(['name' => 'semdata.com.br']);

        $this->assertOrder('/dominios?sort=expires_at', 'domains.data.*.name', ['cedo.com.br', 'tarde.com.br', 'semdata.com.br']);
        $this->assertOrder('/dominios?sort=expires_at&direction=desc', 'domains.data.*.name', ['tarde.com.br', 'cedo.com.br', 'semdata.com.br']);
    }

    public function test_transactions_sort_by_amount_and_cost_center(): void
    {
        $empresa = CostCenter::factory()->create(['name' => 'Empresa']);
        $casa = CostCenter::factory()->create(['name' => 'Casa']);

        Transaction::factory()->payable()->dueIn(5)->create(['description' => 'Cara', 'amount' => 900, 'cost_center_id' => $empresa->id]);
        Transaction::factory()->payable()->dueIn(5)->create(['description' => 'Barata', 'amount' => 100, 'cost_center_id' => $casa->id]);

        $this->assertOrder('/financeiro?sort=amount', 'transactions.data.*.description', ['Barata', 'Cara']);
        $this->assertOrder('/financeiro?sort=amount&direction=desc', 'transactions.data.*.description', ['Cara', 'Barata']);
        $this->assertOrder('/financeiro?sort=cost_center', 'transactions.data.*.description', ['Barata', 'Cara']);
    }

    public function test_tasks_sort_by_priority_and_title(): void
    {
        Task::factory()->open()->dueIn(5)->create(['title' => 'Média', 'priority' => Task::PRIORITY_NORMAL]);
        Task::factory()->open()->dueIn(5)->create(['title' => 'Alta', 'priority' => Task::PRIORITY_URGENT]);
        Task::factory()->open()->dueIn(5)->create(['title' => 'Baixa', 'priority' => Task::PRIORITY_LOW]);

        $this->assertOrder('/tarefas?sort=priority', 'tasks.data.*.title', ['Alta', 'Média', 'Baixa']);
        $this->assertOrder('/tarefas?sort=title', 'tasks.data.*.title', ['Alta', 'Baixa', 'Média']);
    }

    public function test_tasks_sort_by_status_in_progress_order_not_alphabetical(): void
    {
        // Alfabeticamente seria doing, done, pending — o que nao diz nada sobre
        // andamento. A ordem util e a fazer, fazendo, concluida.
        Task::factory()->dueIn(5)->create(['title' => 'Fazendo', 'status' => Task::STATUS_DOING]);
        Task::factory()->dueIn(5)->create(['title' => 'Pronta', 'status' => Task::STATUS_DONE]);
        Task::factory()->dueIn(5)->create(['title' => 'A fazer', 'status' => Task::STATUS_PENDING]);

        $this->assertOrder('/tarefas?sort=status', 'tasks.data.*.title', ['A fazer', 'Fazendo', 'Pronta']);
        $this->assertOrder('/tarefas?sort=status&direction=desc', 'tasks.data.*.title', ['Pronta', 'Fazendo', 'A fazer']);
    }

    public function test_tasks_sort_by_client_and_owner_through_the_relation(): void
    {
        $beta = Client::factory()->create(['name' => 'Beta']);
        $alfa = Client::factory()->create(['name' => 'Alfa']);
        $zeca = User::factory()->create(['name' => 'Zeca']);
        $ana = User::factory()->create(['name' => 'Ana']);

        Task::factory()->open()->dueIn(5)->create(['title' => 'Do Beta', 'client_id' => $beta->id, 'user_id' => $zeca->id]);
        Task::factory()->open()->dueIn(5)->create(['title' => 'Do Alfa', 'client_id' => $alfa->id, 'user_id' => $ana->id]);

        $this->assertOrder('/tarefas?sort=client', 'tasks.data.*.title', ['Do Alfa', 'Do Beta']);
        $this->assertOrder('/tarefas?sort=client&direction=desc', 'tasks.data.*.title', ['Do Beta', 'Do Alfa']);
        $this->assertOrder('/tarefas?sort=owner', 'tasks.data.*.title', ['Do Alfa', 'Do Beta']);
    }

    /** Sem cliente vai para o fim, igual ao prazo vazio. */
    public function test_tasks_without_a_client_stay_last_in_both_directions(): void
    {
        $alfa = Client::factory()->create(['name' => 'Alfa']);

        Task::factory()->open()->dueIn(5)->create(['title' => 'Com cliente', 'client_id' => $alfa->id]);
        Task::factory()->open()->dueIn(5)->create(['title' => 'Sem cliente', 'client_id' => null]);

        $this->assertOrder('/tarefas?sort=client', 'tasks.data.*.title', ['Com cliente', 'Sem cliente']);
        $this->assertOrder('/tarefas?sort=client&direction=desc', 'tasks.data.*.title', ['Com cliente', 'Sem cliente']);
    }

    public function test_cost_centers_sort_by_name_and_usage(): void
    {
        $casa = CostCenter::factory()->create(['name' => 'Casa']);
        $empresa = CostCenter::factory()->create(['name' => 'Empresa']);

        Transaction::factory()->payable()->dueIn(5)->count(2)->create(['cost_center_id' => $casa->id]);
        Transaction::factory()->payable()->dueIn(5)->create(['cost_center_id' => $empresa->id]);

        $url = '/configuracoes/financeiro/centros-de-custo';

        $this->assertOrder($url, 'costCenters.*.name', ['Casa', 'Empresa']);
        $this->assertOrder("{$url}?sort=name&direction=desc", 'costCenters.*.name', ['Empresa', 'Casa']);
        $this->assertOrder("{$url}?sort=usage&direction=desc", 'costCenters.*.name', ['Casa', 'Empresa']);
    }

    /** Ativos primeiro, e alfabético dentro de cada grupo. */
    public function test_cost_centers_sort_by_status_groups_the_inactive_at_the_end(): void
    {
        CostCenter::factory()->create(['name' => 'Zeta ativo', 'active' => true]);
        CostCenter::factory()->create(['name' => 'Alfa inativo', 'active' => false]);
        CostCenter::factory()->create(['name' => 'Beta ativo', 'active' => true]);

        $this->assertOrder(
            '/configuracoes/financeiro/centros-de-custo?sort=status',
            'costCenters.*.name',
            ['Beta ativo', 'Zeta ativo', 'Alfa inativo'],
        );
    }

    public function test_categories_sort_by_name_and_usage(): void
    {
        $aluguel = FinanceCategory::factory()->expense()->create(['name' => 'Aluguel']);
        FinanceCategory::factory()->expense()->create(['name' => 'Zeladoria']);

        Transaction::factory()->payable()->dueIn(5)->create(['finance_category_id' => $aluguel->id]);

        $url = '/configuracoes/financeiro/categorias';

        $this->assertOrder($url, 'categories.*.name', ['Aluguel', 'Zeladoria']);
        $this->assertOrder("{$url}?sort=name&direction=desc", 'categories.*.name', ['Zeladoria', 'Aluguel']);
        $this->assertOrder("{$url}?sort=usage&direction=desc", 'categories.*.name', ['Aluguel', 'Zeladoria']);
    }

    public function test_categories_sort_by_status_groups_the_inactive_at_the_end(): void
    {
        FinanceCategory::factory()->expense()->create(['name' => 'Zeta ativa', 'active' => true]);
        FinanceCategory::factory()->expense()->create(['name' => 'Alfa inativa', 'active' => false]);
        FinanceCategory::factory()->expense()->create(['name' => 'Beta ativa', 'active' => true]);

        $this->assertOrder(
            '/configuracoes/financeiro/categorias?sort=status',
            'categories.*.name',
            ['Beta ativa', 'Zeta ativa', 'Alfa inativa'],
        );
    }

    /**
     * Um callable inacessível some a ordenação em silêncio. Melhor estourar na
     * primeira vez que alguém declarar o método como privado.
     */
    public function test_an_unreachable_callable_target_fails_loudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ListSorting::apply(
            Client::query(),
            ['name' => [PrivateSortTarget::class, 'hidden']],
            'name',
            'asc',
        );
    }

    /** Chave desconhecida cai no padrão em vez de quebrar ou virar SQL. */
    public function test_an_unknown_sort_key_falls_back_to_the_default(): void
    {
        Client::factory()->create(['name' => 'Beta']);
        Client::factory()->create(['name' => 'Alfa']);

        $this->get('/clientes?sort=name);drop+table+clients;--&direction=desc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.sort', 'name')->where('filters.direction', 'desc'));

        $this->assertDatabaseCount('clients', 2);
    }

    public function test_an_unknown_direction_falls_back_to_ascending(): void
    {
        Client::factory()->create(['name' => 'Beta']);
        Client::factory()->create(['name' => 'Alfa']);

        $this->assertOrder('/clientes?sort=name&direction=sideways', 'clients.data.*.name', ['Alfa', 'Beta']);
    }

    public function test_sorting_survives_the_other_filters(): void
    {
        Client::factory()->create(['name' => 'Studio Alfa', 'status' => Client::STATUS_ACTIVE]);
        Client::factory()->create(['name' => 'Studio Beta', 'status' => Client::STATUS_ACTIVE]);
        Client::factory()->create(['name' => 'Outro', 'status' => Client::STATUS_ACTIVE]);

        $this->assertOrder('/clientes?search=Studio&sort=name&direction=desc', 'clients.data.*.name', ['Studio Beta', 'Studio Alfa']);
    }
}

/** Alvo de ordenação com método privado — o erro que o guard precisa pegar. */
class PrivateSortTarget
{
    private static function hidden($query, string $direction): void
    {
        $query->orderBy('name', $direction);
    }
}
