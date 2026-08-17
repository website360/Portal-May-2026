<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Domains\DomainController;
use App\Http\Requests\Clients\ClientRequest;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Project;
use App\Support\ListSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    private const PER_PAGE = 100;

    /** Colunas que a listagem aceita ordenar. Nada fora daqui vira SQL. */
    private const SORTS = [
        'name' => 'name',
        'email' => [self::class, 'orderByEmail'],
        'city' => [self::class, 'orderByCity'],
        'monthly_fee' => [self::class, 'orderByFee'],
        'status' => 'status',
        'created_at' => 'created_at',
    ];

    /**
     * Campo vazio vai para o fim, ordene como ordenar.
     *
     * Cliente sem cidade não é "a primeira cidade do alfabeto": é ausência de
     * dado, e ausência no topo empurra para baixo justamente o que se quer ver.
     *
     * @param  Builder<Client>  $query
     */
    private static function orderByOptional($query, string $column, string $direction): void
    {
        $query->orderByRaw("{$column} is null or {$column} = ''")->orderBy($column, $direction);
    }

    public static function orderByCity($query, string $direction): void
    {
        self::orderByOptional($query, 'city', $direction);
    }

    public static function orderByEmail($query, string $direction): void
    {
        self::orderByOptional($query, 'email', $direction);
    }

    public static function orderByFee($query, string $direction): void
    {
        $query->orderByRaw('monthly_fee is null')->orderBy('monthly_fee', $direction);
    }

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'name');

        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
            ...$sorting,
        ];

        $clients = Client::query()
            ->search($filters['search'])
            ->status($filters['status'])
            ->type($filters['type'])
            ->withCount('projects')
            ->tap(fn ($query) => ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Client $client) => $this->toListItem($client));

        return Inertia::render('clientes/index', [
            'clients' => $clients,
            'filters' => $filters,
            'stats' => [
                'total' => Client::count(),
                'active' => Client::where('status', Client::STATUS_ACTIVE)->count(),
                'inactive' => Client::where('status', Client::STATUS_INACTIVE)->count(),
                'company' => Client::where('type', Client::TYPE_COMPANY)->count(),
                'person' => Client::where('type', Client::TYPE_PERSON)->count(),
            ],
        ]);
    }

    public function show(Client $cliente): Response
    {
        $projects = $cliente->projects()->latest()->get();
        $invoices = $cliente->invoices()->latest('issued_at')->get();

        return Inertia::render('clientes/show', [
            'client' => $this->toListItem($cliente->loadCount('projects')),
            'summary' => [
                'projects' => $projects->count(),
                'openProjects' => $projects->where('status', Project::STATUS_IN_PROGRESS)->count(),
                'billed' => round((float) $invoices->sum('amount'), 2),
                'pending' => round((float) $invoices->whereNull('paid_at')->sum('amount'), 2),
            ],
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'budget' => (float) $project->budget,
                'dueDate' => $project->due_date?->format('d/m/Y'),
            ])->all(),
            'domains' => $cliente->domains()
                ->orderByRaw('expires_at is null')
                ->orderBy('expires_at')
                ->get()
                ->map(fn (Domain $domain) => DomainController::toArray($domain))
                ->all(),
            'invoices' => $invoices->take(8)->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'amount' => (float) $invoice->amount,
                'issuedAt' => $invoice->issued_at?->format('d/m/Y'),
                'paidAt' => $invoice->paid_at?->format('d/m/Y'),
            ])->all(),
        ]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $client = Client::create($this->attributes($request));

        return back()->with('success', "Cliente {$client->name} cadastrado.");
    }

    public function update(ClientRequest $request, Client $cliente): RedirectResponse
    {
        $cliente->update($this->attributes($request, $cliente));

        return back()->with('success', "Cliente {$cliente->name} atualizado.");
    }

    /**
     * Troca só a situação — é o que o seletor inline da listagem chama.
     */
    public function updateStatus(Request $request, Client $cliente): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([Client::STATUS_ACTIVE, Client::STATUS_INACTIVE])],
        ]);

        $cliente->update($validated);

        return back();
    }

    public function destroy(Client $cliente): RedirectResponse
    {
        $name = $cliente->name;

        // Excluir a partir da propria pagina do cliente nao pode voltar para ela:
        // ela deixou de existir. Nos demais casos, back() preserva busca e pagina.
        $cameFromOwnPage = str_starts_with(url()->previous(), route('clientes.show', $cliente));

        if ($cliente->photo_path) {
            Storage::disk('public')->delete($cliente->photo_path);
        }

        $cliente->delete();

        $redirect = $cameFromOwnPage ? redirect()->route('clientes.index') : back();

        return $redirect->with('success', "Cliente {$name} excluído.");
    }

    /**
     * Resolve os campos gravaveis, tratando a foto a parte: `photo` e
     * `remove_photo` sao entradas do formulario, nao colunas.
     *
     * @return array<string, mixed>
     */
    private function attributes(ClientRequest $request, ?Client $client = null): array
    {
        $attributes = $request->validated();
        unset($attributes['photo'], $attributes['remove_photo']);

        $replacing = $request->hasFile('photo');
        $removing = $request->boolean('remove_photo');

        if (! $replacing && ! $removing) {
            return $attributes;
        }

        // Trocar ou remover a foto sempre apaga o arquivo antigo, para o disco
        // nao acumular imagens orfas.
        if ($client?->photo_path) {
            Storage::disk('public')->delete($client->photo_path);
        }

        $attributes['photo_path'] = $replacing
            ? $request->file('photo')->store('clients', 'public')
            : null;

        return $attributes;
    }

    /**
     * O formulario e o mesmo para criar e editar, entao a listagem ja devolve
     * todos os campos — abrir a edicao nao precisa de uma segunda requisicao.
     *
     * @return array<string, mixed>
     */
    private function toListItem(Client $client): array
    {
        return [
            'id' => $client->id,
            'type' => $client->type,
            'name' => $client->name,
            'trade_name' => $client->trade_name,
            'document' => $client->document,
            'photo_url' => $client->photo_url,
            'status' => $client->status,

            'email' => $client->email,
            'phone' => $client->phone,
            'contact_name' => $client->contact_name,
            'contact_role' => $client->contact_role,

            'zip_code' => $client->zip_code,
            'street' => $client->street,
            'number' => $client->number,
            'complement' => $client->complement,
            'district' => $client->district,
            'city' => $client->city,
            'state' => $client->state,

            'segment' => $client->segment,
            'monthly_fee' => $client->monthly_fee === null ? null : (float) $client->monthly_fee,
            'started_at' => $client->started_at?->format('Y-m-d'),
            'notes' => $client->notes,

            'projects_count' => $client->projects_count ?? 0,
        ];
    }
}
