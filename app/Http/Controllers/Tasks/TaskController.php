<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\TaskRequest;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Support\ListSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    private const PER_PAGE = 100;

    /** A chave padrão é a ordem de trabalho; as demais são recortes simples. */
    private const SORTS = [
        'work' => [self::class, 'orderByWork'],
        'due_date' => [self::class, 'orderByDue'],
        'priority' => [self::class, 'orderByPriority'],
        'status' => [self::class, 'orderByStatus'],
        'client' => [self::class, 'orderByClient'],
        'owner' => [self::class, 'orderByOwner'],
        'title' => 'title',
        'created_at' => 'created_at',
    ];

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'work');

        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'mine' => $request->boolean('mine'),
            'overdue' => $request->boolean('overdue'),
            'done_today' => $request->boolean('done_today'),
            ...$sorting,
        ];

        $tasks = Task::query()
            ->with(['client:id,name,trade_name', 'project:id,name', 'user:id,name'])
            ->search($filters['search'])
            ->withStatus($filters['status'])
            ->when($filters['mine'], fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($filters['overdue'], fn ($query) => $query->overdue())
            ->when($filters['done_today'], fn ($query) => $query->completedToday())
            ->tap(fn ($query) => ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Task $task) => self::toArray($task));

        return Inertia::render('tarefas/index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'stats' => $this->stats($request),
            'clients' => Client::pickList(),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public static function orderByWork($query): void
    {
        $query->inWorkOrder();
    }

    /** Sem prazo vai para o fim, independente da direção. */
    public static function orderByDue($query, string $direction): void
    {
        $query->orderByRaw('due_date is null')->orderBy('due_date', $direction);
    }

    public static function orderByPriority($query, string $direction): void
    {
        $rank = "case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end";

        $query->orderByRaw($direction === 'desc' ? "{$rank} desc" : $rank);
    }

    /** Ordem de andamento, não alfabética: a fazer, fazendo, concluída. */
    public static function orderByStatus($query, string $direction): void
    {
        $rank = "case status when 'pending' then 0 when 'doing' then 1 else 2 end";

        $query->orderByRaw($direction === 'desc' ? "{$rank} desc" : $rank);
    }

    /** Sem cliente vai para o fim, como acontece com o prazo vazio. */
    public static function orderByClient($query, string $direction): void
    {
        $query->orderByRaw('client_id is null')
            ->orderBy(Client::select('name')->whereColumn('clients.id', 'tasks.client_id'), $direction);
    }

    public static function orderByOwner($query, string $direction): void
    {
        $query->orderByRaw('user_id is null')
            ->orderBy(User::select('name')->whereColumn('users.id', 'tasks.user_id'), $direction);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $task = Task::create($request->validated());

        if ($task->isDone()) {
            $task->moveTo(Task::STATUS_DONE);
        }

        return back()->with('success', 'Tarefa criada.');
    }

    public function update(TaskRequest $request, Task $tarefa): RedirectResponse
    {
        $data = $request->validated();
        $status = $data['status'];
        unset($data['status']);

        $tarefa->update($data);
        // Passa por moveTo para o completed_at acompanhar a mudança de situação.
        $tarefa->moveTo($status);

        return back()->with('success', 'Tarefa atualizada.');
    }

    /**
     * Troca só a situação — é o que o seletor inline das listagens chama.
     */
    public function updateStatus(Request $request, Task $tarefa): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Task::STATUSES)],
        ]);

        $tarefa->moveTo($validated['status']);

        return back();
    }

    public function destroy(Task $tarefa): RedirectResponse
    {
        $tarefa->delete();

        return back()->with('success', 'Tarefa excluída.');
    }

    /**
     * @return array<string, int>
     */
    private function stats(Request $request): array
    {
        return [
            'pending' => Task::where('status', Task::STATUS_PENDING)->count(),
            'doing' => Task::where('status', Task::STATUS_DOING)->count(),
            'overdue' => Task::query()->overdue()->count(),
            'doneToday' => Task::query()->completedToday()->count(),
            'mine' => Task::query()->open()->where('user_id', $request->user()->id)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'due_date_label' => $task->due_date?->format('d/m/Y'),
            'is_overdue' => $task->isOverdue(),
            'client_id' => $task->client_id,
            'project_id' => $task->project_id,
            'user_id' => $task->user_id,
            'client' => $task->client ? ['id' => $task->client->id, 'name' => $task->client->display_name] : null,
            'project' => $task->project ? ['id' => $task->project->id, 'name' => $task->project->name] : null,
            'user' => $task->user ? ['id' => $task->user->id, 'name' => $task->user->name] : null,
        ];
    }
}
