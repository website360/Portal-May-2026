<?php

namespace App\Http\Controllers\Domains;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domains\DomainRequest;
use App\Models\Client;
use App\Models\Domain;
use App\Support\ListSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    private const PER_PAGE = 100;

    /**
     * A chave padrão guarda a ordem natural da tela: sem vencimento no fim, o
     * resto do mais urgente para o menos.
     */
    private const SORTS = [
        'expires_at' => [self::class, 'orderByExpiration'],
        'name' => 'name',
        'registrar' => 'registrar',
        'managed_by' => 'managed_by',
        'annual_cost' => 'annual_cost',
        'client' => [self::class, 'orderByClient'],
    ];

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'expires_at');

        $filters = [
            'search' => $request->string('search')->toString(),
            'managed_by' => $request->string('managed_by')->toString(),
            'status' => $request->string('status')->toString(),
            ...$sorting,
        ];

        $domains = Domain::query()
            ->with('client:id,name,trade_name,photo_path')
            ->search($filters['search'])
            ->managedBy($filters['managed_by'])
            ->withStatus($filters['status'])
            ->tap(fn ($query) => ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Domain $domain) => $this->toListItem($domain));

        return Inertia::render('dominios/index', [
            'domains' => $domains,
            'filters' => $filters,
            'stats' => $this->stats(),
            'clients' => $this->clientOptions(),
        ]);
    }

    public function store(DomainRequest $request): RedirectResponse
    {
        $domain = Domain::create($request->validated());

        return back()->with('success', "Domínio {$domain->name} cadastrado.");
    }

    public function update(DomainRequest $request, Domain $dominio): RedirectResponse
    {
        $dominio->update($request->validated());

        return back()->with('success', "Domínio {$dominio->name} atualizado.");
    }

    /** Sem data vai sempre para o fim, independente da direção escolhida. */
    public static function orderByExpiration($query, string $direction): void
    {
        $query->orderByRaw('expires_at is null')->orderBy('expires_at', $direction);
    }

    public static function orderByClient($query, string $direction): void
    {
        $query->orderBy(Client::select('name')->whereColumn('clients.id', 'domains.client_id'), $direction);
    }

    /**
     * Troca só quem renova — é o que o seletor inline da listagem chama.
     */
    public function updateManagement(Request $request, Domain $dominio): RedirectResponse
    {
        $validated = $request->validate([
            'managed_by' => ['required', Rule::in([Domain::MANAGED_BY_AGENCY, Domain::MANAGED_BY_CLIENT])],
        ]);

        $dominio->update($validated);

        return back();
    }

    public function destroy(Domain $dominio): RedirectResponse
    {
        $name = $dominio->name;

        $dominio->delete();

        return back()->with('success', "Domínio {$name} excluído.");
    }

    /**
     * Os números de renovação olham só o que a agência administra — é sobre
     * esses que existe algo a fazer. O total conta a carteira inteira.
     *
     * @return array<string, int>
     */
    private function stats(): array
    {
        $agency = fn () => Domain::query()->where('managed_by', Domain::MANAGED_BY_AGENCY);

        return [
            'total' => Domain::count(),
            'agency' => $agency()->count(),
            'client' => Domain::where('managed_by', Domain::MANAGED_BY_CLIENT)->count(),
            'expiring' => $agency()->withStatus(Domain::STATUS_EXPIRING)->count(),
            'expired' => $agency()->withStatus(Domain::STATUS_EXPIRED)->count(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clientOptions(): array
    {
        return Client::query()
            ->orderBy('name')
            ->get(['id', 'name', 'trade_name'])
            ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->display_name])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Domain $domain): array
    {
        return [
            'id' => $domain->id,
            'client_id' => $domain->client_id,
            'name' => $domain->name,
            'registrar' => $domain->registrar,
            'managed_by' => $domain->managed_by,
            'registered_at' => $domain->registered_at?->format('Y-m-d'),
            'expires_at' => $domain->expires_at?->format('Y-m-d'),
            'expires_at_label' => $domain->expires_at?->format('d/m/Y'),
            'auto_renew' => $domain->auto_renew,
            'annual_cost' => $domain->annual_cost === null ? null : (float) $domain->annual_cost,
            'notes' => $domain->notes,
            'status' => $domain->status(),
            'days_left' => $domain->daysLeft(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Domain $domain): array
    {
        return [
            ...self::toArray($domain),
            'client' => [
                'id' => $domain->client->id,
                'name' => $domain->client->display_name,
                'photo_url' => $domain->client->photo_url,
            ],
        ];
    }
}
