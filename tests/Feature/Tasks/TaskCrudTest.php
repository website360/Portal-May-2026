<?php

namespace Tests\Feature\Tasks;

use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_guests_can_not_reach_the_module(): void
    {
        $this->app['auth']->logout();

        $this->get('/tarefas')->assertRedirect(route('login'));
        $this->post('/tarefas', ['title' => 'Qualquer coisa'])->assertRedirect(route('login'));
    }

    public function test_the_list_renders_with_stats(): void
    {
        // Prazo fixo no futuro: o padrão da factory sorteia entre -2 e +4 semanas
        // e faria "atrasadas" oscilar entre as execuções.
        Task::factory(3)->open()->dueIn(10)->create();
        Task::factory(2)->doing()->dueIn(10)->create();
        Task::factory()->done()->create();
        Task::factory(2)->overdue()->create();

        $this->get('/tarefas')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('tarefas/index')
                    ->has('tasks.data', 8)
                    ->where('stats.pending', 5)
                    ->where('stats.doing', 2)
                    ->where('stats.overdue', 2)
                    ->has('clients')
                    ->has('users')
            );
    }

    /** O cadastro rápido manda só o título — o resto tem que assumir padrão. */
    public function test_a_task_can_be_created_with_the_title_alone(): void
    {
        $this->post('/tarefas', ['title' => 'Renovar hospedagem'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Renovar hospedagem',
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_NORMAL,
            'project_id' => null,
            'client_id' => null,
        ]);
    }

    public function test_a_task_without_a_title_is_rejected(): void
    {
        $this->post('/tarefas', ['title' => ''])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_a_task_can_be_linked_to_a_client_and_an_owner(): void
    {
        $client = Client::factory()->create();

        $this->post('/tarefas', [
            'title' => 'Enviar relatório',
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'priority' => Task::PRIORITY_HIGH,
            'due_date' => '2026-09-30',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Enviar relatório',
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'priority' => Task::PRIORITY_HIGH,
        ]);
    }

    public function test_a_task_can_be_updated_and_deleted(): void
    {
        $task = Task::factory()->open()->create(['title' => 'Antigo']);

        $this->put("/tarefas/{$task->id}", [
            'title' => 'Novo título',
            'status' => Task::STATUS_DOING,
            'priority' => Task::PRIORITY_URGENT,
        ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('Novo título', $task->title);
        $this->assertSame(Task::STATUS_DOING, $task->status);

        $this->delete("/tarefas/{$task->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_the_list_can_be_filtered_and_searched(): void
    {
        Task::factory()->open()->create(['title' => 'Alfa', 'user_id' => $this->user->id]);
        Task::factory()->doing()->create(['title' => 'Beta']);
        Task::factory()->done()->create(['title' => 'Gama']);

        $this->get('/tarefas?status=doing')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks.data', 1)->where('tasks.data.0.title', 'Beta'));

        $this->get('/tarefas?search=Gama')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks.data', 1)->where('tasks.data.0.title', 'Gama'));

        $this->get('/tarefas?mine=1')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks.data', 1)->where('tasks.data.0.title', 'Alfa'));
    }

    public function test_the_overdue_filter_returns_only_late_open_tasks(): void
    {
        Task::factory()->overdue()->create(['title' => 'Atrasada']);
        Task::factory()->open()->dueIn(5)->create(['title' => 'No prazo']);
        Task::factory()->open()->create(['title' => 'Sem prazo', 'due_date' => null]);
        // Concluída com prazo vencido não é atrasada: já foi entregue.
        Task::factory()->done()->create(['title' => 'Concluída atrasada', 'due_date' => now()->subDays(5)]);

        $this->get('/tarefas?overdue=1')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('tasks.data', 1)
                ->where('tasks.data.0.title', 'Atrasada')
                ->where('filters.overdue', true)
        );
    }

    public function test_the_done_today_filter_ignores_older_completions(): void
    {
        Task::factory()->done()->create(['title' => 'Hoje', 'completed_at' => now()->subHours(2)]);
        Task::factory()->done()->create(['title' => 'Ontem', 'completed_at' => now()->subDay()]);
        Task::factory()->open()->dueIn(5)->create(['title' => 'Aberta']);

        $this->get('/tarefas?done_today=1')->assertInertia(
            fn (AssertableInertia $page) => $page->has('tasks.data', 1)->where('tasks.data.0.title', 'Hoje')
        );
    }

    /** "Atrasadas" e "Concluídas hoje" combinam com o filtro de responsável. */
    public function test_the_overdue_filter_combines_with_the_owner_filter(): void
    {
        Task::factory()->overdue()->create(['title' => 'Minha atrasada', 'user_id' => $this->user->id]);
        Task::factory()->overdue()->create(['title' => 'De outro']);

        $this->get('/tarefas?overdue=1&mine=1')->assertInertia(
            fn (AssertableInertia $page) => $page->has('tasks.data', 1)->where('tasks.data.0.title', 'Minha atrasada')
        );
    }

    /** Aberto antes de concluído, urgente antes de tranquilo. */
    public function test_the_list_comes_in_work_order(): void
    {
        Task::factory()->done()->create(['title' => 'Concluída']);
        Task::factory()->open()->create(['title' => 'Normal', 'priority' => Task::PRIORITY_NORMAL, 'due_date' => null]);
        Task::factory()->doing()->create(['title' => 'Em andamento', 'priority' => Task::PRIORITY_LOW, 'due_date' => null]);
        Task::factory()->open()->create(['title' => 'Urgente', 'priority' => Task::PRIORITY_URGENT, 'due_date' => null]);

        $this->get('/tarefas')->assertInertia(function (AssertableInertia $page) {
            $titles = collect($page->toArray()['props']['tasks']['data'])->pluck('title')->all();

            $this->assertSame(['Em andamento', 'Urgente', 'Normal', 'Concluída'], $titles);
        });
    }

    public function test_deleting_a_client_deletes_its_tasks(): void
    {
        $client = Client::factory()->create();
        Task::factory(2)->create(['client_id' => $client->id]);

        $this->delete("/clientes/{$client->id}");

        $this->assertDatabaseCount('tasks', 0);
    }
}
