<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ClientShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_not_open_a_client_page(): void
    {
        $client = Client::factory()->create();

        $this->get("/clientes/{$client->id}")->assertRedirect(route('login'));
    }

    public function test_an_unknown_client_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/clientes/999999')
            ->assertNotFound();
    }

    public function test_the_page_renders_the_client_with_its_projects_and_invoices(): void
    {
        $client = Client::factory()->create(['name' => 'Padaria do Bairro Ltda.']);

        Project::factory(2)->inProgress()->create(['client_id' => $client->id]);
        Project::factory()->create(['client_id' => $client->id, 'status' => Project::STATUS_COMPLETED]);

        Invoice::factory()->create(['client_id' => $client->id, 'amount' => 1000.00, 'paid_at' => now()]);
        Invoice::factory()->create(['client_id' => $client->id, 'amount' => 500.00, 'paid_at' => null]);

        $this->actingAs(User::factory()->create())
            ->get("/clientes/{$client->id}")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('clientes/show')
                    ->where('client.id', $client->id)
                    ->where('client.name', 'Padaria do Bairro Ltda.')
                    ->has('projects', 3)
                    ->has('invoices', 2)
                    ->where('summary.projects', 3)
                    ->where('summary.openProjects', 2)
                    ->where('summary.billed', fn ($value) => (float) $value === 1500.0)
                    ->where('summary.pending', fn ($value) => (float) $value === 500.0)
            );
    }

    public function test_the_page_shows_the_full_client_record(): void
    {
        $client = Client::factory()->create([
            'email' => 'contato@cliente.com.br',
            'city' => 'Recife',
            'state' => 'PE',
            'segment' => 'Varejo',
        ]);

        $this->actingAs(User::factory()->create())
            ->get("/clientes/{$client->id}")
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('client.email', 'contato@cliente.com.br')
                    ->where('client.city', 'Recife')
                    ->where('client.state', 'PE')
                    ->where('client.segment', 'Varejo')
                    ->has('client.notes')
                    ->has('client.photo_url')
            );
    }

    public function test_a_client_with_no_history_still_renders(): void
    {
        $client = Client::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/clientes/{$client->id}")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('projects', [])
                    ->where('invoices', [])
                    ->where('summary.projects', 0)
                    ->where('summary.billed', fn ($value) => (float) $value === 0.0)
            );
    }

    /**
     * Excluir a partir da propria pagina nao pode voltar para ela — a pagina
     * deixou de existir e devolveria 404.
     */
    public function test_deleting_from_the_client_page_returns_to_the_list(): void
    {
        $client = Client::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from(route('clientes.show', $client))
            ->delete("/clientes/{$client->id}")
            ->assertRedirect(route('clientes.index'));
    }

    public function test_deleting_from_the_list_keeps_the_current_filters(): void
    {
        $client = Client::factory()->create();
        $listing = route('clientes.index').'?search=alfa&page=1';

        $this->actingAs(User::factory()->create())
            ->from($listing)
            ->delete("/clientes/{$client->id}")
            ->assertRedirect($listing);
    }
}
