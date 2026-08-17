<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractRequest;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Support\ContractDocument;
use App\Support\ContractPlaceholders;
use App\Support\ListSorting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Os contratos dos clientes: o que foi gerado e o que foi arquivado.
 */
class ContractController extends Controller
{
    private const PER_PAGE = 50;

    /** A ordem natural: o mais recente primeiro. */
    private const SORTS = [
        'starts_at' => 'starts_at',
        'number' => 'number',
        'title' => 'title',
        'service' => 'service',
        'value' => 'value',
        'ends_at' => [self::class, 'orderByEnd'],
        'client' => [self::class, 'orderByClient'],
    ];

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'starts_at', 'desc');

        $filters = [
            'search' => $request->string('search')->toString(),
            'statuses' => $this->listOf($request, 'statuses'),
            'clients' => $this->listOf($request, 'clients'),
            'services' => $this->listOf($request, 'services'),
            ...$sorting,
        ];

        return Inertia::render('contratos/index', [
            'contracts' => $this->contracts($filters, $sorting),
            'filters' => $filters,
            'stats' => $this->stats(),
            'clients' => Client::pickList(),
            'services' => $this->serviceOptions(),
        ]);
    }

    /** A tela de gerar: escolhe o serviço, o cliente, e preenche o que falta. */
    public function create(): Response
    {
        return Inertia::render('contratos/gerar', [
            'templates' => ContractTemplate::query()
                ->where('active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (ContractTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'body' => $template->body,
                    // Os campos que este modelo pede além do que já sabemos.
                    'fields' => array_map(fn (string $key) => [
                        'key' => $key,
                        'label' => ContractPlaceholders::labelFor($key),
                    ], $template->customPlaceholders()),
                ])
                ->all(),
            'clients' => Client::pickList(),
            'nextNumber' => Contract::nextNumber(),
        ]);
    }

    /**
     * O texto como ele vai ficar, sem salvar nada.
     *
     * Sai do mesmo código que gera o contrato de verdade — uma prévia calculada
     * na tela precisaria repetir em TypeScript a formatação de CNPJ, o valor por
     * extenso e a data por extenso, e divergiria do resultado no primeiro
     * detalhe. Prévia que mente é pior que prévia nenhuma.
     */
    public function preview(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $data = $request->validate([
            'contract_template_id' => ['required', 'exists:contract_templates,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'title' => ['nullable', 'string', 'max:180'],
            'service' => ['nullable', 'string', 'max:120'],
            'value' => ['nullable', 'numeric'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'variables' => ['array'],
            'variables.*' => ['nullable', 'string', 'max:1000'],
            // 'pdf' abre o documento como ele fica; sem isso, só o texto.
            'formato' => ['nullable', 'in:texto,pdf'],
        ]);

        $template = ContractTemplate::findOrFail($data['contract_template_id']);
        $contract = $this->previewContract($template, $data);

        if (($data['formato'] ?? 'texto') === 'pdf') {
            return (new ContractDocument($contract))->inline();
        }

        return response()->json(['body' => $contract->body]);
    }

    /**
     * Um contrato de mentira, montado com o que está no formulário.
     *
     * Nunca é salvo: serve só para renderizar. Sem cliente escolhido ainda, os
     * marcadores dele seguem à mostra no texto, que é o comportamento certo —
     * o vazio esconderia o que falta preencher.
     *
     * @param  array<string, mixed>  $data
     */
    private function previewContract(ContractTemplate $template, array $data): Contract
    {
        $client = blank($data['client_id'] ?? null)
            ? new Client(['name' => 'Cliente ainda não escolhido', 'trade_name' => 'Cliente ainda não escolhido'])
            : Client::findOrFail($data['client_id']);

        $contract = new Contract([
            'title' => $data['title'] ?? $template->name,
            'service' => $data['service'] ?? $template->name,
            'value' => $data['value'] ?? null,
            'starts_at' => $data['starts_at'] ?? now()->toDateString(),
            'ends_at' => $data['ends_at'] ?? null,
        ]);

        $contract->number = Contract::nextNumber();
        $contract->setRelation('client', $client);
        $contract->setRelation('template', $template);

        $values = blank($data['client_id'] ?? null) ? [] : ContractPlaceholders::valuesFor($contract);
        $contract->body = ContractPlaceholders::render($template->body, [...$values, ...($data['variables'] ?? [])]);

        return $contract;
    }

    public function store(ContractRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $contract = new Contract($data);
        $contract->number = Contract::nextNumber();
        $contract->body = $this->renderBody($contract, $data);
        $contract->save();

        if ($request->hasFile('pdf')) {
            $contract->update(['pdf_path' => $request->file('pdf')->store('contratos', 'public')]);
        }

        return to_route('contratos.index')->with('success', "Contrato {$contract->number} gerado.");
    }

    public function update(ContractRequest $request, Contract $contrato): RedirectResponse
    {
        $data = $request->validated();

        $contrato->fill($data);

        /*
         * O texto só é regerado enquanto o contrato não foi assinado. Depois
         * disso, o papel que as partes assinaram é o que vale — reescrevê-lo a
         * partir de um modelo editado depois seria trocar o contrato.
         */
        if ($contrato->signed_at === null && $contrato->contract_template_id !== null) {
            $contrato->body = $this->renderBody($contrato, $data);
        }

        $contrato->save();

        if ($request->hasFile('pdf')) {
            $this->replaceAttachment($contrato, $request->file('pdf')->store('contratos', 'public'));
        }

        return back()->with('success', "Contrato {$contrato->number} atualizado.");
    }

    /**
     * O PDF: o anexado quando existe, senão o gerado do texto.
     *
     * `?ver=1` abre no visualizador do navegador em vez de baixar — é como se
     * confere um contrato sem encher a pasta de downloads.
     */
    public function pdf(Request $request, Contract $contrato): \Symfony\Component\HttpFoundation\Response
    {
        $document = new ContractDocument($contrato);
        $inline = $request->boolean('ver');

        if ($contrato->hasAttachment() && Storage::disk('public')->exists($contrato->pdf_path)) {
            $file = Storage::disk('public')->path($contrato->pdf_path);

            return $inline
                ? response()->file($file, ['Content-Disposition' => 'inline; filename="'.$document->filename().'"'])
                : response()->download($file, $document->filename());
        }

        abort_if(blank($contrato->body), 404, 'Este contrato não tem texto nem arquivo.');

        return $inline ? $document->inline() : $document->stream();
    }

    /** Marca como assinado, com a data em que foi de fato. */
    public function sign(Request $request, Contract $contrato): RedirectResponse
    {
        $data = $request->validate([
            'signed_at' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'signed_at.before_or_equal' => 'A assinatura não pode ser no futuro.',
        ], [
            'signed_at' => 'data da assinatura',
        ]);

        $contrato->update($data);

        return back()->with('success', "Contrato {$contrato->number} marcado como assinado.");
    }

    /**
     * Cancela sem apagar.
     *
     * Um contrato cancelado continua tendo existido, e o histórico com o cliente
     * depende disso — por isso cancelar não é o mesmo que excluir.
     */
    public function cancel(Contract $contrato): RedirectResponse
    {
        $contrato->update(['cancelled_at' => $contrato->cancelled_at === null ? now() : null]);

        return back()->with(
            'success',
            $contrato->cancelled_at === null
                ? "Contrato {$contrato->number} reativado."
                : "Contrato {$contrato->number} cancelado."
        );
    }

    public function destroy(Contract $contrato): RedirectResponse
    {
        $number = $contrato->number;

        $this->deleteAttachment($contrato);
        $contrato->delete();

        return back()->with('success', "Contrato {$number} excluído.");
    }

    /** Remove só o PDF anexado, mantendo o contrato e o texto. */
    public function removeAttachment(Contract $contrato): RedirectResponse
    {
        $this->deleteAttachment($contrato);
        $contrato->update(['pdf_path' => null]);

        return back()->with('success', 'Arquivo removido. O contrato continua aqui.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderBody(Contract $contract, array $data): ?string
    {
        $template = ContractTemplate::find($data['contract_template_id'] ?? null);

        if ($template === null) {
            return $contract->body;
        }

        // setRelation: o contrato ainda pode não estar salvo, e os marcadores
        // precisam do cliente para se resolverem.
        $contract->setRelation('client', Client::findOrFail($data['client_id']));

        return ContractPlaceholders::render(
            $template->body,
            [...ContractPlaceholders::valuesFor($contract), ...($data['variables'] ?? [])]
        );
    }

    private function replaceAttachment(Contract $contract, string $path): void
    {
        $this->deleteAttachment($contract);
        $contract->update(['pdf_path' => $path]);
    }

    private function deleteAttachment(Contract $contract): void
    {
        if ($contract->hasAttachment()) {
            Storage::disk('public')->delete($contract->pdf_path);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array{sort: string, direction: string}  $sorting
     */
    private function contracts(array $filters, array $sorting): LengthAwarePaginator
    {
        return Contract::query()
            ->with('client:id,name,trade_name,photo_path')
            ->search($filters['search'])
            ->withStatuses($filters['statuses'])
            ->ofClients($filters['clients'])
            ->ofServices($filters['services'])
            ->tap(fn ($query) => ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Contract $contract) => self::toArray($contract));
    }

    /**
     * @return list<string>
     */
    private function listOf(Request $request, string $key): array
    {
        $raw = $request->input($key, []);

        return array_values(array_filter(is_array($raw) ? $raw : [$raw], fn ($v) => is_string($v) && $v !== ''));
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'total' => Contract::count(),
            'active' => Contract::withStatuses([Contract::STATUS_ACTIVE, Contract::STATUS_EXPIRING])->count(),
            'expiring' => Contract::withStatuses([Contract::STATUS_EXPIRING])->count(),
            'draft' => Contract::withStatuses([Contract::STATUS_DRAFT])->count(),
        ];
    }

    /**
     * Os serviços que já foram contratados — oferecer um que não existe seria
     * oferecer um recorte vazio.
     *
     * @return list<array{value: string, label: string}>
     */
    private function serviceOptions(): array
    {
        return Contract::query()
            ->distinct()
            ->orderBy('service')
            ->pluck('service')
            ->map(fn (string $service) => ['value' => $service, 'label' => $service])
            ->all();
    }

    /** Prazo indeterminado vai sempre para o fim, independente da direção. */
    public static function orderByEnd($query, string $direction): void
    {
        $query->orderByRaw('ends_at is null')->orderBy('ends_at', $direction);
    }

    public static function orderByClient($query, string $direction): void
    {
        $query->orderBy(Client::select('name')->whereColumn('clients.id', 'contracts.client_id'), $direction);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'client_id' => $contract->client_id,
            'contract_template_id' => $contract->contract_template_id,
            'number' => $contract->number,
            'title' => $contract->title,
            'service' => $contract->service,
            'value' => $contract->value === null ? null : (float) $contract->value,
            'starts_at' => $contract->starts_at->format('Y-m-d'),
            'starts_label' => $contract->starts_at->format('d/m/Y'),
            'ends_at' => $contract->ends_at?->format('Y-m-d'),
            'ends_label' => $contract->ends_at?->format('d/m/Y'),
            'days_left' => $contract->daysLeft(),
            'status' => $contract->status(),
            'signed_at' => $contract->signed_at?->format('Y-m-d'),
            'signed_label' => $contract->signed_at?->format('d/m/Y'),
            'cancelled' => $contract->cancelled_at !== null,
            'has_attachment' => $contract->hasAttachment(),
            'has_body' => filled($contract->body),
            'body' => $contract->body,
            'variables' => $contract->variables,
            'notes' => $contract->notes,
            'client' => [
                'id' => $contract->client->id,
                'name' => $contract->client->display_name,
                'photo_url' => $contract->client->photo_url,
            ],
        ];
    }
}
