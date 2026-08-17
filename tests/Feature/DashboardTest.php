<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_authenticated_users_see_the_four_indicators(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('dashboard')
                    ->has('kpis', 4)
                    ->has('revenueSeries', 12)
                    ->has('recentProjects')
                    ->has('activities')
                    ->where('kpis.0.label', 'Clientes ativos')
                    ->where('kpis.1.label', 'Projetos em andamento')
                    ->where('kpis.2.label', 'Faturamento do mês')
                    ->where('kpis.3.label', 'Tarefas pendentes')
            );
    }

    public function test_the_indicators_count_real_records(): void
    {
        $user = User::factory()->create();

        $client = Client::factory()->create(['status' => Client::STATUS_ACTIVE]);
        Client::factory(2)->inactive()->create();

        $project = Project::factory()->inProgress()->create(['client_id' => $client->id]);
        Project::factory()->create(['client_id' => $client->id, 'status' => Project::STATUS_COMPLETED]);

        Task::factory(3)->create(['project_id' => $project->id, 'status' => Task::STATUS_PENDING]);
        Task::factory()->create(['project_id' => $project->id, 'status' => Task::STATUS_DONE]);

        Invoice::factory()->create([
            'client_id' => $client->id,
            'amount' => 1500.00,
            'issued_at' => Carbon::now()->startOfMonth()->addDay(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('kpis.0.value', 1)
                    ->where('kpis.1.value', 1)
                    ->where('kpis.2.value', fn ($value) => (float) $value === 1500.0)
                    ->where('kpis.3.value', 3)
            );
    }

    public function test_the_dashboard_works_with_an_empty_database(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('kpis.0.value', 0)
                    // Sem base de comparacao a variacao vem nula, e o card esconde o badge.
                    ->where('kpis.0.delta', null)
                    ->where('recentProjects', [])
                    ->where('activities', [])
            );
    }

    public function test_the_revenue_series_covers_twelve_months_in_order(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(function (AssertableInertia $page) {
                $series = collect($page->toArray()['props']['revenueSeries']);
                $months = $series->pluck('month')->all();

                $this->assertCount(12, $series);
                $this->assertSame(Carbon::now()->startOfMonth()->subMonths(11)->format('Y-m'), $months[0]);
                $this->assertSame(Carbon::now()->format('Y-m'), $months[11]);
                $this->assertSame(collect($months)->sort()->values()->all(), $months);
            });
    }
}
