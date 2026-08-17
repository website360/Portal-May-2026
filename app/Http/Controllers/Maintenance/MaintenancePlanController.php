<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\MaintenancePlanRequest;
use App\Models\Client;
use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Support\ListSorting;
use App\Support\MaintenanceChecklist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção preventiva: a lista dos planos e o histórico do que já foi feito.
 *
 * As duas abas são a mesma tela porque respondem à mesma pergunta em tempos
 * diferentes — "o que falta fazer" e "o que foi feito".
 */
class MaintenancePlanController extends Controller
{
    private const PER_PAGE = 100;

    public const TAB_PLANS = 'planos';

    public const TAB_HISTORY = 'historico';

    /** A ordem natural: o mais parado no topo, que é por onde se começa o dia. */
    private const PLAN_SORTS = [
        'last_performed_at' => [self::class, 'orderByLastPerformed'],
        'site_url' => 'site_url',
        'client' => [self::class, 'orderByClient'],
    ];

    private const HISTORY_SORTS = [
        'performed_at' => 'performed_at',
        'site' => [self::class, 'orderBySite'],
        'client' => [self::class, 'orderByHistoryClient'],
    ];

    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() === self::TAB_HISTORY ? self::TAB_HISTORY : self::TAB_PLANS;

        $sorting = $tab === self::TAB_HISTORY
            ? ListSorting::resolve($request, self::HISTORY_SORTS, 'performed_at', 'desc')
            : ListSorting::resolve($request, self::PLAN_SORTS, 'last_performed_at');

        $filters = [
            'tab' => $tab,
            'search' => $request->string('search')->toString(),
            'statuses' => $this->listOf($request, 'statuses'),
            'clients' => $this->listOf($request, 'clients'),
            'users' => $this->listOf($request, 'users'),
            'reports' => $this->listOf($request, 'reports'),
            'month' => $request->string('month')->toString(),
            ...$sorting,
        ];

        return Inertia::render('manutencao/index', [
            'filters' => $filters,
            'stats' => $this->stats(),
            'plans' => $tab === self::TAB_PLANS ? $this->plans($filters, $sorting) : null,
            'history' => $tab === self::TAB_HISTORY ? $this->history($filters, $sorting) : null,
            'clients' => Client::pickList(),
            'checklist' => $this->checklistOptions(),
            // Só quem aparece no histórico: uma lista de todos os usuários
            // ofereceria recortes que devolvem lista vazia.
            'executors' => $this->executorOptions(),
            'months' => $this->monthOptions(),
        ]);
    }

    public function store(MaintenancePlanRequest $request): RedirectResponse
    {
        $plan = MaintenancePlan::create($request->validated());

        return back()->with('success', "Plano de {$plan->site_url} criado.");
    }

    public function update(MaintenancePlanRequest $request, MaintenancePlan $plano): RedirectResponse
    {
        $plano->update($request->validated());

        return back()->with('success', "Plano de {$plano->site_url} atualizado.");
    }

    public function destroy(MaintenancePlan $plano): RedirectResponse
    {
        $site = $plano->site_url;

        $plano->delete();

        return back()->with('success', "Plano de {$site} excluído, junto com o histórico dele.");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{sort: string, direction: string}  $sorting
     */
    private function plans(array $filters, array $sorting): LengthAwarePaginator
    {
        return MaintenancePlan::query()
            ->with('client:id,name,trade_name,photo_path,phone')
            ->search($filters['search'])
            ->withStatuses($filters['statuses'])
            ->ofClients($filters['clients'])
            ->tap(fn ($query) => ListSorting::apply($query, self::PLAN_SORTS, $sorting['sort'], $sorting['direction']))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (MaintenancePlan $plan) => self::planToArray($plan));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{sort: string, direction: string}  $sorting
     */
    private function history(array $filters, array $sorting): LengthAwarePaginator
    {
        return Maintenance::query()
            ->with(['plan.client:id,name,trade_name,photo_path', 'user:id,name'])
            ->search($filters['search'])
            ->ofClients($filters['clients'])
            ->byUsers($filters['users'])
            ->withReports($filters['reports'])
            ->inMonth($filters['month'])
            ->tap(fn ($query) => ListSorting::apply($query, self::HISTORY_SORTS, $sorting['sort'], $sorting['direction']))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Maintenance $maintenance) => self::maintenanceToArray($maintenance));
    }

    /**
     * Um filtro múltiplo vindo da URL.
     *
     * Todos aqui aceitam mais de uma escolha — "atrasadas e pendentes juntas" é
     * a visão de quem monta a semana, e nada marcado significa "todos".
     *
     * @return list<string>
     */
    private function listOf(Request $request, string $key): array
    {
        $raw = $request->input($key, []);

        return array_values(array_filter(is_array($raw) ? $raw : [$raw], fn ($value) => is_string($value) && $value !== ''));
    }

    /**
     * Quem já registrou alguma manutenção, para o filtro de executor.
     *
     * @return list<array{value: string, label: string}>
     */
    private function executorOptions(): array
    {
        return User::query()
            ->whereHas('maintenances')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name])
            ->all();
    }

    /**
     * Os meses que têm manutenção registrada, do mais recente para o mais antigo.
     *
     * @return list<string>
     */
    private function monthOptions(): array
    {
        return Maintenance::query()
            ->orderByDesc('performed_at')
            ->pluck('performed_at')
            ->map(fn ($date) => $date->format('Y-m'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        $active = fn () => MaintenancePlan::query()->where('active', true);

        return [
            'active' => $active()->count(),
            'late' => $active()->withStatuses([MaintenancePlan::STATUS_LATE])->count(),
            'pending' => $active()->withStatuses([MaintenancePlan::STATUS_PENDING])->count(),
            'done' => $active()->withStatuses([MaintenancePlan::STATUS_DONE])->count(),
        ];
    }

    /**
     * O checklist e os resultados possíveis, para a tela montar o formulário sem
     * repetir a lista em TypeScript.
     *
     * @return array<string, mixed>
     */
    private function checklistOptions(): array
    {
        return [
            'items' => collect(MaintenanceChecklist::ITEMS)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'results' => collect(MaintenanceChecklist::RESULTS)
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    /** Nunca revisado vai para o topo em ordem crescente: é o mais urgente. */
    public static function orderByLastPerformed($query, string $direction): void
    {
        $query->mostUrgent($direction);
    }

    public static function orderByClient($query, string $direction): void
    {
        $query->orderBy(
            Client::select('name')->whereColumn('clients.id', 'maintenance_plans.client_id'),
            $direction
        );
    }

    public static function orderBySite($query, string $direction): void
    {
        $query->orderBy(
            MaintenancePlan::select('site_url')->whereColumn('maintenance_plans.id', 'maintenances.maintenance_plan_id'),
            $direction
        );
    }

    public static function orderByHistoryClient($query, string $direction): void
    {
        $query->orderBy(
            MaintenancePlan::select('name')
                ->join('clients', 'clients.id', '=', 'maintenance_plans.client_id')
                ->whereColumn('maintenance_plans.id', 'maintenances.maintenance_plan_id'),
            $direction
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function planToArray(MaintenancePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'client_id' => $plan->client_id,
            'site_url' => $plan->site_url,
            'last_performed_at' => $plan->last_performed_at?->format('Y-m-d'),
            'last_performed_label' => $plan->last_performed_at?->format('d/m/Y'),
            // O mês da última, escrito: "julho de 2026" lê melhor que uma data
            // solta quando a pergunta é sobre competência, e não sobre o dia.
            'last_month_label' => $plan->last_performed_at?->translatedFormat('F \d\e Y'),
            'pending_months' => $plan->pendingMonths(),
            'status' => $plan->status(),
            'active' => $plan->active,
            'notes' => $plan->notes,
            'client' => [
                'id' => $plan->client->id,
                'name' => $plan->client->display_name,
                'photo_url' => $plan->client->photo_url,
                'has_phone' => filled($plan->client->phone),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function maintenanceToArray(Maintenance $maintenance): array
    {
        return [
            'id' => $maintenance->id,
            'plan_id' => $maintenance->maintenance_plan_id,
            'performed_at' => $maintenance->performed_at->format('Y-m-d'),
            'performed_label' => $maintenance->performed_at->format('d/m/Y'),
            'items' => $maintenance->items,
            'done_count' => $maintenance->doneCount(),
            'skipped_count' => $maintenance->skippedCount(),
            'total_count' => count($maintenance->items ?? []),
            'notes' => $maintenance->notes,
            'whatsapp_sent_at' => $maintenance->whatsapp_sent_at?->format('d/m/Y H:i'),
            'whatsapp_error' => $maintenance->whatsapp_error,
            'user' => $maintenance->user?->name,
            'site_url' => $maintenance->plan->site_url,
            'client' => [
                'id' => $maintenance->plan->client->id,
                'name' => $maintenance->plan->client->display_name,
                'photo_url' => $maintenance->plan->client->photo_url,
            ],
        ];
    }
}
