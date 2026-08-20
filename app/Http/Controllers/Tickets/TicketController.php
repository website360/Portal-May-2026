<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\TicketAssignedAlert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * O núcleo do atendimento: tickets com conversa, status, prioridade e responsável.
 */
class TicketController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'priority' => $request->string('priority')->toString(),
            'assignee' => $request->string('assignee')->toString(),
            'client' => $request->string('client')->toString(),
        ];

        $tickets = $this->filtered($filters)
            ->orderByDesc('last_reply_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Ticket $ticket) => self::toArray($ticket));

        return Inertia::render('tickets/index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'stats' => $this->stats(),
            'clients' => Client::pickList(),
            'agents' => $this->agents(),
            'categories' => $this->categories(),
        ]);
    }

    /**
     * O quadro: tickets em colunas por situação, para arrastar entre elas.
     *
     * Os ativos vêm todos; os fechados, só os 25 mais recentes — uma coluna de
     * fechados que cresce sem parar deixaria de ser um quadro e viraria arquivo.
     */
    public function board(): Response
    {
        $with = ['client:id,name,trade_name,photo_path', 'assignee:id,name'];

        $active = Ticket::query()->with($with)->withCount('messages')
            ->where('status', '!=', Ticket::STATUS_CLOSED)
            ->orderByDesc('last_reply_at')->orderByDesc('id')
            ->get();

        $closed = Ticket::query()->with($with)->withCount('messages')
            ->where('status', Ticket::STATUS_CLOSED)
            ->orderByDesc('closed_at')->orderByDesc('id')
            ->limit(25)
            ->get();

        $columns = [];
        foreach (Ticket::STATUSES as $status) {
            $source = $status === Ticket::STATUS_CLOSED ? $closed : $active->where('status', $status);
            $columns[$status] = $source->map(fn (Ticket $ticket) => self::toArray($ticket))->values();
        }

        return Inertia::render('tickets/kanban', [
            'columns' => $columns,
            'stats' => $this->stats(),
            'clients' => Client::pickList(),
            'agents' => $this->agents(),
        ]);
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load([
            'client:id,name,trade_name,photo_path',
            'assignee:id,name',
            'opener:id,name',
            'messages.user:id,name',
            'messages.attachments',
        ]);

        return Inertia::render('tickets/show', [
            'ticket' => self::detail($ticket),
            'clients' => Client::pickList(),
            'agents' => $this->agents(),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:20000'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $ticket = new Ticket([
            'subject' => $data['subject'],
            'client_id' => $data['client_id'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'opened_by' => $request->user()->id,
            'status' => Ticket::STATUS_OPEN,
            'priority' => $data['priority'],
            'category' => $data['category'] ?? null,
            'channel' => 'manual',
            'last_reply_at' => now(),
        ]);
        $ticket->number = Ticket::nextNumber();
        $ticket->save();

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'internal' => false,
        ]);

        if ($ticket->assignee_id && $ticket->assignee_id !== $request->user()->id) {
            $this->notifyAssignee($ticket);
        }

        return to_route('tickets.show', $ticket)->with('success', "Ticket {$ticket->number} aberto.");
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'internal' => ['boolean'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $message = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'internal' => (bool) ($data['internal'] ?? false),
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $message->attachments()->create([
                'path' => $file->store('tickets', 'public'),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        $ticket->last_reply_at = now();

        // A primeira resposta pública marca o tempo de primeira resposta.
        if ($ticket->first_response_at === null && ! $message->internal) {
            $ticket->first_response_at = now();
        }

        $ticket->save();

        return back()->with('success', 'Resposta enviada.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:180'],
            'status' => ['sometimes', Rule::in(Ticket::STATUSES)],
            'priority' => ['sometimes', Rule::in(Ticket::PRIORITIES)],
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'client_id' => ['sometimes', 'nullable', 'exists:clients,id'],
            'category' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $previousAssignee = $ticket->assignee_id;

        if (array_key_exists('status', $data)) {
            $ticket->moveTo($data['status']);
            unset($data['status']);
        }

        $ticket->fill($data);
        $ticket->save();

        // Reatribuir para outra pessoa avisa quem recebeu — nunca a si mesmo.
        if ($ticket->assignee_id
            && $ticket->assignee_id !== $previousAssignee
            && $ticket->assignee_id !== $request->user()->id) {
            $this->notifyAssignee($ticket);
        }

        return back()->with('success', 'Ticket atualizado.');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $ticket->delete();

        return to_route('tickets.index')->with('success', 'Ticket excluído.');
    }

    /**
     * Avisa o responsável — por e-mail, ou o que o modelo em Configurações ›
     * Mensagens definir. Não interrompe a resposta se o envio falhar.
     */
    private function notifyAssignee(Ticket $ticket): void
    {
        $assignee = User::find($ticket->assignee_id);

        if ($assignee !== null) {
            (new TicketAssignedAlert($ticket->loadMissing('client'), $assignee))->send();
        }
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filtered(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return Ticket::query()
            ->with(['client:id,name,trade_name,photo_path', 'assignee:id,name'])
            ->withCount('messages')
            ->search($filters['search'])
            ->withStatus($filters['status'])
            ->ofPriority($filters['priority'])
            ->ofAssignee($filters['assignee'])
            ->ofClient($filters['client']);
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        $abertos = fn () => Ticket::query()->whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_DOING]);

        return [
            'open' => $abertos()->count(),
            'unassigned' => $abertos()->whereNull('assignee_id')->count(),
            'urgent' => $abertos()->where('priority', Ticket::PRIORITY_URGENT)->count(),
            'resolved' => Ticket::where('status', Ticket::STATUS_RESOLVED)->count(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function agents(): array
    {
        return User::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        return Ticket::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'channel' => $ticket->channel,
            'messages_count' => $ticket->messages_count ?? $ticket->messages()->count(),
            'last_reply_label' => $ticket->last_reply_at?->diffForHumans(),
            'created_label' => $ticket->created_at->format('d/m/Y'),
            'client' => $ticket->client ? [
                'id' => $ticket->client->id,
                'name' => $ticket->client->display_name,
                'photo_url' => $ticket->client->photo_url,
            ] : null,
            'assignee' => $ticket->assignee ? ['id' => $ticket->assignee->id, 'name' => $ticket->assignee->name] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Ticket $ticket): array
    {
        return [
            ...self::toArray($ticket),
            'opener' => $ticket->opener ? ['id' => $ticket->opener->id, 'name' => $ticket->opener->name] : null,
            'created_at_label' => $ticket->created_at->format('d/m/Y H:i'),
            'client_id' => $ticket->client_id,
            'assignee_id' => $ticket->assignee_id,
            'messages' => $ticket->messages->map(fn (TicketMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'internal' => $message->internal,
                'user' => $message->user ? ['id' => $message->user->id, 'name' => $message->user->name] : null,
                'created_label' => $message->created_at->diffForHumans(),
                'created_at' => $message->created_at->format('d/m/Y H:i'),
                'attachments' => $message->attachments->map(fn (TicketAttachment $attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'url' => $attachment->url(),
                    'size' => $attachment->size,
                ])->all(),
            ])->all(),
        ];
    }
}
