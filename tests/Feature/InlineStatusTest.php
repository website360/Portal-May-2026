<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O seletor inline das listagens fala com uma rota PATCH por recurso. Cada uma
 * aceita mexer num campo só — é o que impede que "trocar a situação" vire uma
 * porta para escrever em qualquer coluna.
 */
class InlineStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_guests_can_not_change_anything_inline(): void
    {
        $this->app['auth']->logout();

        $task = Task::factory()->open()->create();
        $client = Client::factory()->create();

        $this->patch("/tarefas/{$task->id}/situacao", ['status' => Task::STATUS_DONE])->assertRedirect(route('login'));
        $this->patch("/clientes/{$client->id}/situacao", ['status' => Client::STATUS_INACTIVE])->assertRedirect(route('login'));
    }

    public function test_a_task_status_can_be_changed_inline(): void
    {
        $task = Task::factory()->open()->create();

        $this->patch("/tarefas/{$task->id}/situacao", ['status' => Task::STATUS_DOING])->assertSessionHasNoErrors();

        $this->assertSame(Task::STATUS_DOING, $task->refresh()->status);
    }

    /** Concluir carimba a data; reabrir apaga — é daí que sai "concluídas hoje". */
    public function test_completing_and_reopening_keeps_the_completion_date_honest(): void
    {
        $task = Task::factory()->open()->create();

        $this->patch("/tarefas/{$task->id}/situacao", ['status' => Task::STATUS_DONE]);
        $this->assertNotNull($task->refresh()->completed_at);

        $this->patch("/tarefas/{$task->id}/situacao", ['status' => Task::STATUS_PENDING]);
        $this->assertNull($task->refresh()->completed_at);
    }

    public function test_an_unknown_task_status_is_rejected(): void
    {
        $task = Task::factory()->open()->create();

        $this->patch("/tarefas/{$task->id}/situacao", ['status' => 'arquivada'])->assertSessionHasErrors('status');

        $this->assertSame(Task::STATUS_PENDING, $task->refresh()->status);
    }

    public function test_a_client_status_can_be_changed_inline(): void
    {
        $client = Client::factory()->create(['status' => Client::STATUS_ACTIVE]);

        $this->patch("/clientes/{$client->id}/situacao", ['status' => Client::STATUS_INACTIVE])->assertSessionHasNoErrors();

        $this->assertSame(Client::STATUS_INACTIVE, $client->refresh()->status);
    }

    /** A rota de situação não pode servir para editar o resto do cadastro. */
    public function test_the_client_status_route_ignores_other_fields(): void
    {
        $client = Client::factory()->create(['name' => 'Nome original', 'status' => Client::STATUS_ACTIVE]);

        $this->patch("/clientes/{$client->id}/situacao", [
            'status' => Client::STATUS_INACTIVE,
            'name' => 'Nome invadido',
            'monthly_fee' => 999999,
        ])->assertSessionHasNoErrors();

        $client->refresh();

        $this->assertSame(Client::STATUS_INACTIVE, $client->status);
        $this->assertSame('Nome original', $client->name);
    }

    public function test_an_unknown_client_status_is_rejected(): void
    {
        $client = Client::factory()->create(['status' => Client::STATUS_ACTIVE]);

        $this->patch("/clientes/{$client->id}/situacao", ['status' => 'arquivado'])->assertSessionHasErrors('status');

        $this->assertSame(Client::STATUS_ACTIVE, $client->refresh()->status);
    }

    public function test_domain_management_can_be_changed_inline(): void
    {
        $domain = Domain::factory()->managedByAgency()->create();

        $this->patch("/dominios/{$domain->id}/gestao", ['managed_by' => Domain::MANAGED_BY_CLIENT])->assertSessionHasNoErrors();

        $this->assertSame(Domain::MANAGED_BY_CLIENT, $domain->refresh()->managed_by);
    }

    public function test_the_domain_management_route_ignores_other_fields(): void
    {
        $domain = Domain::factory()->managedByAgency()->create(['name' => 'original.com.br']);

        $this->patch("/dominios/{$domain->id}/gestao", [
            'managed_by' => Domain::MANAGED_BY_CLIENT,
            'name' => 'invadido.com.br',
        ])->assertSessionHasNoErrors();

        $domain->refresh();

        $this->assertSame(Domain::MANAGED_BY_CLIENT, $domain->managed_by);
        $this->assertSame('original.com.br', $domain->name);
    }

    public function test_an_unknown_domain_management_is_rejected(): void
    {
        $domain = Domain::factory()->managedByAgency()->create();

        $this->patch("/dominios/{$domain->id}/gestao", ['managed_by' => 'terceiro'])->assertSessionHasErrors('managed_by');

        $this->assertSame(Domain::MANAGED_BY_AGENCY, $domain->refresh()->managed_by);
    }
}
