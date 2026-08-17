<?php

namespace Tests\Feature\Domains;

use App\Models\Client;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DomainCrudTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create(['name' => 'Padaria do Bairro Ltda.']);
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client->id,
            'name' => 'padariadobairro.com.br',
            'registrar' => 'Registro.br',
            'managed_by' => Domain::MANAGED_BY_AGENCY,
            'registered_at' => '2024-03-01',
            'expires_at' => '2027-03-01',
            'auto_renew' => true,
            'annual_cost' => '59.90',
            'notes' => 'Painel no e-mail do financeiro.',
        ], $overrides);
    }

    public function test_guests_can_not_reach_the_module(): void
    {
        $this->app['auth']->logout();

        $this->get('/dominios')->assertRedirect(route('login'));
        $this->post('/dominios', $this->payload())->assertRedirect(route('login'));
    }

    public function test_the_list_renders_with_stats(): void
    {
        Domain::factory(3)->managedByAgency()->for($this->client)->create();
        Domain::factory(2)->managedByClient()->for($this->client)->create();

        $this->get('/dominios')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('dominios/index')
                    ->has('domains.data', 5)
                    ->where('stats.total', 5)
                    ->where('stats.agency', 3)
                    ->where('stats.client', 2)
                    ->has('clients')
            );
    }

    public function test_a_domain_can_be_created_and_linked_to_a_client(): void
    {
        $this->post('/dominios', $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('domains', [
            'client_id' => $this->client->id,
            'name' => 'padariadobairro.com.br',
            'managed_by' => Domain::MANAGED_BY_AGENCY,
            'auto_renew' => true,
        ]);
    }

    public function test_the_domain_is_normalised_before_saving(): void
    {
        $this->post('/dominios', $this->payload(['name' => 'HTTPS://WWW.Padaria.COM.BR/']))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('domains', ['name' => 'www.padaria.com.br']);
    }

    public function test_an_invalid_domain_is_rejected(): void
    {
        foreach (['sem-ponto', 'com barra/aqui', 'espaco no meio.com'] as $invalid) {
            $this->post('/dominios', $this->payload(['name' => $invalid]))->assertSessionHasErrors('name');
        }

        $this->assertDatabaseCount('domains', 0);
    }

    public function test_the_domain_can_not_repeat(): void
    {
        Domain::factory()->for($this->client)->create(['name' => 'padariadobairro.com.br']);

        $this->post('/dominios', $this->payload())->assertSessionHasErrors('name');
    }

    public function test_a_domain_needs_an_existing_client(): void
    {
        $this->post('/dominios', $this->payload(['client_id' => 999999]))->assertSessionHasErrors('client_id');
    }

    public function test_the_expiry_can_not_precede_the_registration(): void
    {
        $this->post('/dominios', $this->payload([
            'registered_at' => '2026-01-01',
            'expires_at' => '2025-01-01',
        ]))->assertSessionHasErrors('expires_at');
    }

    public function test_a_domain_can_be_updated(): void
    {
        $domain = Domain::factory()->for($this->client)->managedByAgency()->create();

        $this->put("/dominios/{$domain->id}", $this->payload([
            'name' => $domain->name,
            'managed_by' => Domain::MANAGED_BY_CLIENT,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(Domain::MANAGED_BY_CLIENT, $domain->refresh()->managed_by);
    }

    public function test_a_domain_can_be_deleted(): void
    {
        $domain = Domain::factory()->for($this->client)->create();

        $this->delete("/dominios/{$domain->id}")->assertSessionHas('success');

        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    }

    public function test_deleting_a_client_deletes_its_domains(): void
    {
        Domain::factory(2)->for($this->client)->create();

        $this->delete("/clientes/{$this->client->id}");

        $this->assertDatabaseCount('domains', 0);
    }

    public function test_the_list_can_be_filtered_by_management_and_searched(): void
    {
        Domain::factory()->for($this->client)->managedByAgency()->create(['name' => 'nosso.com.br']);
        Domain::factory()->for($this->client)->managedByClient()->create(['name' => 'deles.com.br']);

        $this->get('/dominios?managed_by=agency')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('domains.data', 1)->where('domains.data.0.name', 'nosso.com.br'));

        $this->get('/dominios?search=deles')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('domains.data', 1)->where('domains.data.0.name', 'deles.com.br'));

        // A busca também alcança o nome do cliente.
        $this->get('/dominios?search=Padaria')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('domains.data', 2));
    }

    public function test_the_client_page_lists_its_domains(): void
    {
        Domain::factory(2)->for($this->client)->create();
        Domain::factory()->create();

        $this->get("/clientes/{$this->client->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('domains', 2));
    }
}
