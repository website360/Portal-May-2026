<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => Client::TYPE_COMPANY,
            'name' => 'Padaria do Bairro Ltda.',
            'trade_name' => 'Pão Quente',
            'document' => '12.345.678/0001-90',
            'status' => Client::STATUS_ACTIVE,
            'email' => 'contato@paoquente.com.br',
            'phone' => '(11) 98888-7777',
            'contact_name' => 'Joana Prado',
            'contact_role' => 'Sócia',
            'zip_code' => '01310-100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'complement' => 'Sala 42',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'segment' => 'Alimentação',
            'monthly_fee' => '2500.00',
            'started_at' => '2025-03-01',
            'notes' => 'Cliente indicado pela Valência.',
        ], $overrides);
    }

    public function test_guests_can_not_reach_the_client_module(): void
    {
        $this->get('/clientes')->assertRedirect(route('login'));
        $this->post('/clientes', $this->payload())->assertRedirect(route('login'));
    }

    public function test_the_list_renders_with_stats(): void
    {
        Client::factory(14)->create(['status' => Client::STATUS_ACTIVE]);
        Client::factory(3)->inactive()->create();

        $this->actingAs(User::factory()->create())
            ->get('/clientes')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('clientes/index')
                    ->has('clients.data', 17)
                    ->where('clients.total', 17)
                    ->where('clients.last_page', 1)
                    ->where('stats.total', 17)
                    ->where('stats.active', 14)
                    ->where('stats.inactive', 3)
            );
    }

    public function test_the_list_shows_one_hundred_clients_per_page(): void
    {
        Client::factory(101)->create();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/clientes')
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('clients.data', 100)
                    ->where('clients.per_page', 100)
                    ->where('clients.last_page', 2)
                    ->where('clients.from', 1)
                    ->where('clients.to', 100)
            );

        $this->actingAs($user)
            ->get('/clientes?page=2')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('clients.data', 1)->where('clients.from', 101));
    }

    public function test_a_client_can_be_created_with_every_step_filled(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->from('/clientes')
            ->post('/clientes', $this->payload());

        $response->assertSessionHasNoErrors()->assertRedirect('/clientes');

        $this->assertDatabaseHas('clients', [
            'name' => 'Padaria do Bairro Ltda.',
            'trade_name' => 'Pão Quente',
            'document' => '12.345.678/0001-90',
            'city' => 'São Paulo',
            'state' => 'SP',
            'monthly_fee' => '2500.00',
            'status' => Client::STATUS_ACTIVE,
        ]);
    }

    public function test_only_name_type_and_status_are_required(): void
    {
        $minimal = [
            'type' => Client::TYPE_PERSON,
            'name' => 'Maria Souza',
            'status' => Client::STATUS_ACTIVE,
        ];

        $this->actingAs(User::factory()->create())
            ->post('/clientes', $minimal)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', ['name' => 'Maria Souza', 'type' => Client::TYPE_PERSON]);
    }

    public function test_a_client_without_a_name_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/clientes', $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_the_document_can_not_repeat(): void
    {
        Client::factory()->create(['document' => '12.345.678/0001-90']);

        $this->actingAs(User::factory()->create())
            ->post('/clientes', $this->payload())
            ->assertSessionHasErrors('document');
    }

    public function test_a_client_keeps_its_own_document_when_edited(): void
    {
        $client = Client::factory()->create(['document' => '12.345.678/0001-90']);

        $this->actingAs(User::factory()->create())
            ->put("/clientes/{$client->id}", $this->payload(['name' => 'Novo nome']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Novo nome']);
    }

    public function test_empty_strings_are_stored_as_null(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/clientes', $this->payload(['trade_name' => '', 'notes' => '', 'monthly_fee' => '']));

        $this->assertDatabaseHas('clients', [
            'name' => 'Padaria do Bairro Ltda.',
            'trade_name' => null,
            'notes' => null,
            'monthly_fee' => null,
        ]);
    }

    public function test_a_client_can_be_deleted_along_with_its_projects(): void
    {
        $client = Client::factory()->create();
        Project::factory(2)->create(['client_id' => $client->id]);

        $this->actingAs(User::factory()->create())
            ->delete("/clientes/{$client->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        Client::factory()->create(['name' => 'Studio Alfa', 'status' => Client::STATUS_ACTIVE]);
        Client::factory()->create(['name' => 'Studio Beta', 'status' => Client::STATUS_INACTIVE]);
        Client::factory()->create(['name' => 'Outra Coisa', 'status' => Client::STATUS_ACTIVE]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/clientes?search=Studio')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('clients.data', 2));

        $this->actingAs($user)
            ->get('/clientes?search=Studio&status=active')
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('clients.data', 1)
                    ->where('clients.data.0.name', 'Studio Alfa')
            );
    }

    public function test_the_list_can_be_filtered_by_client_type(): void
    {
        Client::factory()->create(['name' => 'Padaria Ltda', 'type' => Client::TYPE_COMPANY]);
        Client::factory()->create(['name' => 'Outra Ltda', 'type' => Client::TYPE_COMPANY]);
        Client::factory()->create(['name' => 'Maria Souza', 'type' => Client::TYPE_PERSON]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/clientes?type=person')
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('clients.data', 1)
                    ->where('clients.data.0.name', 'Maria Souza')
                    // As contagens dos indicadores nao seguem o filtro: elas sao
                    // o que da para clicar em seguida.
                    ->where('stats.company', 2)
                    ->where('stats.person', 1)
            );

        $this->actingAs($user)
            ->get('/clientes?type=company')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('clients.data', 2));
    }

    public function test_an_unknown_client_type_filter_is_ignored(): void
    {
        Client::factory(3)->create();

        $this->actingAs(User::factory()->create())
            ->get('/clientes?type=alienigena')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('clients.data', 3));
    }

    public function test_the_type_and_status_filters_combine(): void
    {
        Client::factory()->create(['name' => 'PJ ativa', 'type' => Client::TYPE_COMPANY, 'status' => Client::STATUS_ACTIVE]);
        Client::factory()->create(['name' => 'PJ inativa', 'type' => Client::TYPE_COMPANY, 'status' => Client::STATUS_INACTIVE]);
        Client::factory()->create(['name' => 'PF ativa', 'type' => Client::TYPE_PERSON, 'status' => Client::STATUS_ACTIVE]);

        $this->actingAs(User::factory()->create())
            ->get('/clientes?type=company&status=active')
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('clients.data', 1)
                    ->where('clients.data.0.name', 'PJ ativa')
            );
    }

    public function test_the_search_also_matches_email_and_document(): void
    {
        Client::factory()->create(['name' => 'Alfa', 'email' => 'financeiro@alfa.com.br', 'document' => '11.111.111/0001-11']);
        Client::factory()->create(['name' => 'Beta', 'email' => 'contato@beta.com.br', 'document' => '22.222.222/0001-22']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/clientes?search=financeiro')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('clients.data.0.name', 'Alfa'));

        $this->actingAs($user)
            ->get('/clientes?search=22.222.222')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('clients.data.0.name', 'Beta'));
    }
}
