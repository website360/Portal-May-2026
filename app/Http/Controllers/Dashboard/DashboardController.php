<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Recurrence;
use App\Models\Task;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'kpis' => $this->kpis(),
            'revenueSeries' => $this->revenueSeries(),
            'recentProjects' => $this->recentProjects(),
            'activities' => $this->activities(),
            'domainAlerts' => $this->domainAlerts(),
            'endingRecurrences' => $this->endingRecurrences(),
        ]);
    }

    /**
     * Dominios sob gestao da agencia ja vencidos ou a vencer. So esses exigem
     * acao nossa — os que o cliente administra ficam de fora do aviso.
     *
     * @return array<string, mixed>
     */
    /**
     * Contratos chegando ao fim.
     *
     * O aviso precisa chegar enquanto ainda há uma cobrança pela frente — saber
     * em agosto que setembro fecha o ciclo dá tempo de renegociar; a mesma
     * notícia em setembro chega junto com o problema.
     *
     * @return array<int, array<string, mixed>>
     */
    private function endingRecurrences(): array
    {
        return Recurrence::ending()
            ->with('client:id,name,trade_name')
            ->orderBy('next_due_at')
            ->get()
            ->filter(fn (Recurrence $r) => $r->isEnding())
            ->take(6)
            ->map(fn (Recurrence $r) => [
                'id' => $r->id,
                'description' => $r->description,
                'client' => $r->client?->display_name,
                'amount' => (float) $r->amount,
                'next_due_at' => $r->next_due_at->toDateString(),
                'remaining' => $r->remaining(),
                'is_last' => $r->isLastCharge(),
                'type' => $r->type,
            ])
            ->values()
            ->all();
    }

    private function domainAlerts(): array
    {
        $domains = Domain::query()
            ->with('client:id,name,trade_name')
            ->where('managed_by', Domain::MANAGED_BY_AGENCY)
            ->needingAttention()
            ->get();

        return [
            'total' => $domains->count(),
            'items' => $domains->take(5)->map(fn (Domain $domain) => [
                'id' => $domain->id,
                'name' => $domain->name,
                'client' => $domain->client?->display_name ?? 'Sem cliente',
                'clientId' => $domain->client_id,
                'expiresAt' => $domain->expires_at?->format('d/m/Y'),
                'daysLeft' => $domain->daysLeft(),
                'status' => $domain->status(),
            ])->all(),
        ];
    }

    /**
     * Quatro indicadores, cada um com o valor atual e o equivalente ao fim do
     * mes anterior — e dai a variacao percentual.
     *
     * @return list<array<string, mixed>>
     */
    private function kpis(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $previousMonth = $startOfMonth->copy()->subMonth();

        $activeClients = Client::where('status', Client::STATUS_ACTIVE)->count();
        $activeClientsBefore = Client::where('status', Client::STATUS_ACTIVE)
            ->where('created_at', '<', $startOfMonth)
            ->count();

        $openProjects = Project::where('status', Project::STATUS_IN_PROGRESS)->count();
        $openProjectsBefore = Project::where('status', Project::STATUS_IN_PROGRESS)
            ->where('created_at', '<', $startOfMonth)
            ->count();

        $revenue = $this->received($startOfMonth, $startOfMonth->copy()->endOfMonth());
        $revenueBefore = $this->received($previousMonth, $previousMonth->copy()->endOfMonth());

        // "Pendente" aqui é tudo que não foi concluído — inclui o que está em andamento.
        $pendingTasks = Task::query()->open()->count();
        $pendingTasksBefore = Task::query()->open()
            ->where('created_at', '<', $startOfMonth)
            ->count();

        return [
            [
                'key' => 'clients',
                'label' => 'Clientes ativos',
                'value' => $activeClients,
                'format' => 'number',
                'delta' => $this->delta($activeClients, $activeClientsBefore),
                'goodWhen' => 'up',
            ],
            [
                'key' => 'projects',
                'label' => 'Projetos em andamento',
                'value' => $openProjects,
                'format' => 'number',
                'delta' => $this->delta($openProjects, $openProjectsBefore),
                'goodWhen' => 'up',
            ],
            [
                'key' => 'revenue',
                'label' => 'Faturamento do mês',
                'value' => round($revenue, 2),
                'format' => 'currency',
                'delta' => $this->delta($revenue, $revenueBefore),
                'goodWhen' => 'up',
            ],
            [
                'key' => 'tasks',
                'label' => 'Tarefas pendentes',
                'value' => $pendingTasks,
                'format' => 'number',
                'delta' => $this->delta($pendingTasks, $pendingTasksBefore),
                'goodWhen' => 'down',
            ],
        ];
    }

    /**
     * Variacao percentual contra o mes anterior. Null quando nao ha base de
     * comparacao — o card entao esconde o badge em vez de mostrar 0% ou infinito.
     */
    private function delta(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Doze meses de faturamento. O agrupamento e feito em PHP, e nao em SQL,
     * para o mesmo codigo rodar no MySQL local e no SQLite dos testes.
     *
     * @return list<array<string, mixed>>
     */
    /** O que entrou de fato num período: recebíveis pagos, pelo valor pago. */
    private function received(Carbon $from, Carbon $to): float
    {
        return (float) Transaction::query()
            ->where('type', Transaction::TYPE_RECEIVABLE)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->get(['amount', 'paid_amount'])
            ->sum(fn (Transaction $t) => (float) ($t->paid_amount ?? $t->amount));
    }

    private function revenueSeries(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(11);

        $totals = Transaction::query()
            ->where('type', Transaction::TYPE_RECEIVABLE)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_amount', 'paid_at'])
            ->groupBy(fn (Transaction $t) => $t->paid_at->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum(fn (Transaction $t) => (float) ($t->paid_amount ?? $t->amount)));

        $series = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $series[] = [
                'month' => $key,
                'label' => ucfirst(str_replace('.', '', $month->translatedFormat('M'))),
                'revenue' => round((float) $totals->get($key, 0.0), 2),
            ];
        }

        return $series;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentProjects(): array
    {
        return Project::with('client:id,name')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'client' => $project->client?->name ?? 'Sem cliente',
                'status' => $project->status,
                'budget' => (float) $project->budget,
                'dueDate' => $project->due_date?->format('d/m/Y'),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activities(): array
    {
        return Activity::with('user:id,name')
            ->latest()
            ->take(7)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'user' => $activity->user?->name ?? 'Sistema',
                'description' => $activity->description,
                'when' => $activity->created_at->diffForHumans(),
            ])
            ->all();
    }
}
