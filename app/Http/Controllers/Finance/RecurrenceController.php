<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CostCenter;
use App\Models\FinanceCategory;
use App\Models\Recurrence;
use App\Support\ListSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecurrenceController extends Controller
{
    /** @var array<string, string|callable> */
    private const SORTS = [
        'next_due_at' => 'next_due_at',
        'description' => 'description',
        'amount' => 'amount',
        'client' => [self::class, 'orderByClient'],
    ];

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'next_due_at');

        $query = Recurrence::query()->with(['client:id,name,trade_name', 'costCenter:id,name,color', 'category:id,name,color']);

        ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']);

        $recurrences = $query->get()->map(fn (Recurrence $r) => self::toArray($r));

        return Inertia::render('financeiro/recorrencias', [
            'recurrences' => $recurrences,
            'filters' => $sorting,
            'stats' => [
                'total' => $recurrences->count(),
                'running' => $recurrences->where('running', true)->count(),
                'ending' => $recurrences->where('is_ending', true)->count(),
            ],
            'costCenters' => CostCenter::where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
            'categories' => FinanceCategory::where('active', true)->orderBy('name')->get(['id', 'name', 'type', 'color']),
            'clients' => Client::pickList(),
        ]);
    }

    public static function orderByClient($query, string $direction): void
    {
        $query->orderByRaw('client_id is null')
            ->orderBy(Client::select('name')->whereColumn('clients.id', 'recurrences.client_id'), $direction);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Recurrence $recurrence): array
    {
        $remaining = $recurrence->remaining();

        return [
            'id' => $recurrence->id,
            'type' => $recurrence->type,
            'description' => $recurrence->description,
            'amount' => (float) $recurrence->amount,
            'interval' => $recurrence->interval,
            'next_due_at' => $recurrence->next_due_at->toDateString(),
            'ends_at' => $recurrence->ends_at?->toDateString(),
            'active' => $recurrence->active,

            // Derivados: a tela não recalcula regra de negócio.
            'running' => $recurrence->isRunning(),
            'remaining' => $remaining,
            'is_last' => $recurrence->isLastCharge(),
            'is_ending' => $recurrence->isEnding(),
            'has_ended' => $recurrence->hasEnded(),

            'client' => $recurrence->client ? ['id' => $recurrence->client->id, 'name' => $recurrence->client->display_name] : null,
            'cost_center' => $recurrence->costCenter
                ? ['id' => $recurrence->costCenter->id, 'name' => $recurrence->costCenter->name, 'color' => $recurrence->costCenter->color]
                : null,
            'category' => $recurrence->category
                ? ['id' => $recurrence->category->id, 'name' => $recurrence->category->name, 'color' => $recurrence->category->color]
                : null,
            'counterpart' => $recurrence->counterpart,
            'notes' => $recurrence->notes,
        ];
    }

    public function update(Request $request, Recurrence $recorrencia): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'interval' => ['required', Rule::in(Recurrence::INTERVALS)],
            'next_due_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:next_due_at'],
            'active' => ['nullable', 'boolean'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'finance_category_id' => ['nullable', 'exists:finance_categories,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'counterpart' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'description' => 'descrição',
            'amount' => 'valor',
            'interval' => 'intervalo',
            'next_due_at' => 'próximo vencimento',
            'ends_at' => 'data final',
        ]);

        $recorrencia->update($data);

        return back()->with('success', 'Recorrência atualizada.');
    }

    /**
     * Renova por mais N ciclos, opcionalmente com valor novo.
     *
     * O valor novo vale das próximas cobranças em diante — as já emitidas não
     * são reescritas, senão a conciliação do que o cliente já pagou deixaria de
     * bater.
     */
    public function renew(Request $request, Recurrence $recorrencia): RedirectResponse
    {
        $data = $request->validate([
            'cycles' => ['required', 'integer', 'min:1', 'max:120'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ], [], ['cycles' => 'ciclos', 'amount' => 'valor']);

        $recorrencia->renew((int) $data['cycles'], isset($data['amount']) ? (float) $data['amount'] : null);
        $recorrencia->refresh();

        /*
         * Renovar também lança as cobranças que passaram a caber.
         *
         * Estender só a data deixaria o contrato renovado e o financeiro sem
         * nada de novo — que foi exatamente o que aconteceu. As cobranças saem
         * com o valor de agora, então o reajuste vale a partir daqui sem tocar
         * nas que já foram emitidas.
         */
        $antes = $recorrencia->transactions()->count();

        for ($i = 0; $i < 600 && $recorrencia->generateNext() !== null; $i++) {
            // generateNext avança sozinho; para quando o contrato acaba.
        }

        $novas = $recorrencia->transactions()->count() - $antes;

        return back()->with(
            'success',
            $novas === 0
                ? 'Renovada. Nenhuma cobrança nova a lançar por enquanto.'
                : "Renovada: {$novas} ".($novas === 1 ? 'cobrança lançada' : 'cobranças lançadas').'.'
        );
    }

    /**
     * Encerrar não apaga o que já foi cobrado: as contas emitidas continuam no
     * financeiro, só param de nascer novas.
     */
    public function destroy(Recurrence $recorrencia): RedirectResponse
    {
        $name = $recorrencia->description;

        $recorrencia->delete();

        return back()->with('success', "Recorrência {$name} encerrada. As cobranças já emitidas continuam no financeiro.");
    }
}
