<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\TransactionRequest;
use App\Models\Client;
use App\Models\CostCenter;
use App\Models\FinanceCategory;
use App\Models\PaymentMethod;
use App\Models\Recurrence;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Support\ListSorting;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    private const PER_PAGE = 100;

    private const SORTS = [
        'due_date' => [self::class, 'orderByDue'],
        'description' => 'description',
        'amount' => 'amount',
        'paid_at' => 'paid_at',
        'cost_center' => [self::class, 'orderByCostCenter'],
        'category' => [self::class, 'orderByCategory'],
        'client' => [self::class, 'orderByClient'],
    ];

    /**
     * Normaliza um filtro que aceita vários valores.
     *
     * Aceita `?status=paid`, `?status[]=paid&status[]=overdue` e
     * `?status=paid,overdue` — link antigo continua funcionando.
     *
     * @return list<string>
     */
    private static function asList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $items), fn ($item) => $item !== ''));
    }

    /** Sem cliente vai para o fim; a ordem é pela marca, como o rótulo. */
    public static function orderByClient($query, string $direction): void
    {
        $query->orderByRaw('client_id is null')
            ->orderBy(
                Client::selectRaw('coalesce(nullif(trade_name, ""), name)')->whereColumn('clients.id', 'transactions.client_id'),
                $direction,
            );
    }

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'due_date');

        $filters = [
            'search' => $request->string('search')->toString(),
            /*
             * Listas, não valores únicos: dá para pedir "em aberto" e "vencida"
             * juntas. A URL aceita as duas formas — `status=paid` continua
             * valendo — para não invalidar link salvo nem chamada antiga.
             */
            'type' => self::asList($request->input('type')),
            'status' => self::asList($request->input('status')),
            'cost_center_id' => self::asList($request->input('cost_center_id')),
            'finance_category_id' => self::asList($request->input('finance_category_id')),
            // Sem mês escolhido, o mês corrente: é o recorte de quem abre a tela
            // para saber o que vence agora, não o extrato desde sempre.
            'month' => $request->has('month') ? $request->string('month')->toString() : Carbon::today()->format('Y-m'),
            ...$sorting,
        ];

        $transactions = Transaction::query()
            // trade_name junto: o rótulo do cliente é a marca, não a razão social.
            ->with(['costCenter:id,name,color', 'category:id,name,color,type', 'client:id,name,trade_name', 'paymentMethod:id,name', 'supplier:id,name,trade_name'])
            ->search($filters['search'])
            ->ofTypes($filters['type'])
            ->withStatuses($filters['status'])
            ->when($filters['cost_center_id'], fn ($query, $ids) => $query->whereIn('cost_center_id', $ids))
            ->when($filters['finance_category_id'], fn ($query, $ids) => $query->whereIn('finance_category_id', $ids))
            ->inMonth($filters['month'])
            ->tap(fn ($query) => ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => self::toArray($transaction));

        return Inertia::render('financeiro/index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'summary' => $this->summary($filters),
            'costCenters' => CostCenter::where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
            'categories' => FinanceCategory::where('active', true)->orderBy('name')->get(['id', 'name', 'type', 'color']),
            'clients' => Client::pickList(),
            'paymentMethods' => PaymentMethod::where('active', true)->orderBy('name')->get(['id', 'name', 'color']),
            'suppliers' => Supplier::pickList(),
            'months' => $this->availableMonths(),
            'projected' => $this->projected($filters),
        ]);
    }

    /**
     * Cobranças de recorrência que ainda não viraram lançamento.
     *
     * Sem isso o mês seguinte parece vazio: a regra existe, o dinheiro está
     * combinado, mas a conta só nasce perto do vencimento. Estas linhas são
     * projeção — não têm id, não recebem baixa, e somem quando a conta de
     * verdade é gerada.
     *
     * @param  array<string, string>  $filters
     * @return list<array<string, mixed>>
     */
    private function projected(array $filters): array
    {
        if ($filters['search'] !== '' || $filters['status'] !== []) {
            return [];
        }

        /*
         * Sem mês escolhido, projeta o próximo ano.
         *
         * Recém-criada a recorrência, a cobrança do mês corrente já virou conta
         * e não há o que projetar ali — o que faria a tela parecer não ter
         * entendido o contrato. Em "todos os períodos" o combinado inteiro
         * aparece de uma vez.
         */
        if ($filters['month'] === '') {
            $start = Carbon::today();
            $end = $start->copy()->addYear();
        } else {
            $start = Carbon::createFromFormat('Y-m-d', $filters['month'].'-01')->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        $recurrences = Recurrence::query()
            ->where('active', true)
            ->with(['client:id,name,trade_name', 'costCenter:id,name,color', 'category:id,name,color'])
            ->when($filters['type'], fn ($query, $types) => $query->whereIn('type', $types))
            ->when($filters['cost_center_id'], fn ($query, $ids) => $query->whereIn('cost_center_id', $ids))
            ->when($filters['finance_category_id'], fn ($query, $ids) => $query->whereIn('finance_category_id', $ids))
            ->get();

        // O que já virou conta no período não pode aparecer duas vezes.
        $materialized = Transaction::whereNotNull('recurrence_id')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->get(['recurrence_id', 'due_date'])
            ->map(fn (Transaction $t) => $t->recurrence_id.'|'.$t->due_date->toDateString())
            ->flip();

        $rows = [];

        foreach ($recurrences as $recurrence) {
            foreach ($recurrence->occurrencesBetween($start, $end) as $date) {
                if ($materialized->has($recurrence->id.'|'.$date->toDateString())) {
                    continue;
                }

                $rows[] = [
                    'recurrence_id' => $recurrence->id,
                    'type' => $recurrence->type,
                    'description' => $recurrence->description,
                    'amount' => (float) $recurrence->amount,
                    'due_date' => $date->toDateString(),
                    'due_date_label' => $date->format('d/m/Y'),
                    'client' => $recurrence->client?->display_name,
                    'cost_center' => $recurrence->costCenter
                        ? ['name' => $recurrence->costCenter->name, 'color' => $recurrence->costCenter->color]
                        : null,
                    'category' => $recurrence->category?->name,
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => $a['due_date'] <=> $b['due_date']);

        return $rows;
    }

    /**
     * Meses que realmente têm lançamento, do mais novo para o mais antigo, mais
     * o mês corrente. Oferecer um intervalo fixo mostraria meses vazios.
     *
     * @return list<string>
     */
    private function availableMonths(): array
    {
        $months = Transaction::query()
            ->selectRaw('due_date')
            ->distinct()
            ->orderByDesc('due_date')
            ->pluck('due_date')
            ->map(fn ($date) => Carbon::parse($date)->format('Y-m'));

        /*
         * Mais os doze meses à frente.
         *
         * Só listar meses que já têm lançamento parece razoável até o financeiro
         * estar quase vazio: com uma conta única, o filtro fica com uma opção só
         * e dá a impressão de estar quebrado. Olhar para a frente também é o que
         * se quer com recorrência, cujas cobranças ainda nem foram geradas.
         */
        $ahead = collect(range(0, 12))->map(fn (int $step) => Carbon::today()->addMonthsNoOverflow($step)->format('Y-m'));

        return $months->merge($ahead)->unique()->sortDesc()->values()->all();
    }

    /** Pagas descem para o fim: o que exige ação vem primeiro. */
    public static function orderByDue($query, string $direction): void
    {
        $query->orderByRaw('paid_at is not null')->orderBy('due_date', $direction)->orderByDesc('id');
    }

    public static function orderByCostCenter($query, string $direction): void
    {
        $query->orderBy(CostCenter::select('name')->whereColumn('cost_centers.id', 'transactions.cost_center_id'), $direction);
    }

    public static function orderByCategory($query, string $direction): void
    {
        $query->orderBy(
            FinanceCategory::select('name')->whereColumn('finance_categories.id', 'transactions.finance_category_id'),
            $direction,
        );
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['description'] = $this->describe($data);
        $installments = (int) ($data['installments'] ?? 1);

        /*
         * Sem `repeat` declarado, mais de uma parcela já significa parcelamento.
         * O formulário sempre manda o modo, mas inferir mantém funcionando quem
         * chama só com `installments` — e evita que a omissão vire, em silêncio,
         * um lançamento único no lugar das doze parcelas pedidas.
         */
        $repeat = $data['repeat'] ?? ($installments > 1 ? 'installments' : 'once');
        $interval = $data['interval'] ?? null;
        $occurrences = isset($data['occurrences']) ? (int) $data['occurrences'] : null;
        unset($data['installments'], $data['repeat'], $data['interval'], $data['occurrences']);

        if ($repeat === 'recurring') {
            return $this->storeRecurring($data, $interval, $occurrences);
        }

        if ($repeat !== 'installments' || $installments <= 1) {
            Transaction::create($data);

            return back()->with('success', 'Lançamento criado.');
        }

        // Parcelamento: um lançamento por mês, todos com o mesmo series_id para
        // dar para reconhecer que nasceram juntos.
        $seriesId = (string) Str::uuid();
        $firstDue = Carbon::parse($data['due_date']);

        foreach (range(1, $installments) as $number) {
            Transaction::create([
                ...$data,
                'due_date' => $firstDue->copy()->addMonthsNoOverflow($number - 1),
                'description' => "{$data['description']} ({$number}/{$installments})",
                'series_id' => $seriesId,
                'installment' => $number,
                'installments' => $installments,
            ]);
        }

        return back()->with('success', "{$installments} parcelas criadas.");
    }

    /**
     * Cria a recorrência e materializa só a primeira cobrança.
     *
     * A diferença para o parcelamento está aqui: parcelado gera as N contas de
     * uma vez porque a dívida inteira já existe. Recorrente gera uma; as outras
     * nascem quando chega a hora, e o valor pode mudar na renovação sem
     * reescrever o que já foi emitido.
     *
     * @param  array<string, mixed>  $data
     */
    private function storeRecurring(array $data, ?string $interval, ?int $occurrences): RedirectResponse
    {
        $interval ??= Recurrence::ANNUAL;

        /*
         * A data de fim é calculada, não digitada. Doze cobranças mensais a
         * partir de 10/08 terminam em 10/07 do ano seguinte — a última cabe
         * dentro do intervalo, por isso o menos um.
         */
        $endsAt = $occurrences === null
            ? null
            : Carbon::parse($data['due_date'])
                ->addMonthsNoOverflow(Recurrence::MONTHS[$interval] * ($occurrences - 1))
                ->toDateString();

        $recurrence = Recurrence::create([
            'type' => $data['type'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'interval' => $interval,
            'next_due_at' => $data['due_date'],
            'ends_at' => $endsAt,
            'active' => true,
            'cost_center_id' => $data['cost_center_id'] ?? null,
            'finance_category_id' => $data['finance_category_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'counterpart' => $data['counterpart'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        /*
         * Lança as cobranças do contrato de uma vez.
         *
         * O desenho original materializava só a próxima, para não travar valores
         * de 2027. Na prática isso deixava o financeiro parecendo vazio: quem
         * fecha um contrato de doze meses quer ver as doze contas. Renovar e
         * reajustar continuam funcionando — o valor novo vale das cobranças
         * ainda não emitidas em diante.
         *
         * Sem data de fim, lança um ano; o agendador cuida do resto.
         */
        $total = $occurrences ?? 12;

        for ($number = 1; $number <= $total; $number++) {
            if ($recurrence->generateNext($number, $occurrences) === null) {
                break;
            }
        }

        $criadas = $recurrence->transactions()->count();

        return back()->with(
            'success',
            "Recorrência criada, com {$criadas} ".($criadas === 1 ? 'cobrança lançada' : 'cobranças lançadas').'.'
        );
    }

    /**
     * Descrição de quem não digitou nenhuma.
     *
     * A listagem inteira se orienta por esse texto — deixar em branco daria uma
     * linha muda no meio do extrato. Categoria e fornecedor são exatamente o
     * que a pessoa escreveria à mão, então servem de rótulo.
     *
     * @param  array<string, mixed>  $data
     */
    private function describe(array $data): string
    {
        $typed = trim((string) ($data['description'] ?? ''));

        if ($typed !== '') {
            return $typed;
        }

        /*
         * A categoria de propósito não entra aqui.
         *
         * Ela tem coluna própria na listagem; usá-la como descrição faria a
         * mesma palavra aparecer duas vezes na linha, sem acrescentar nada.
         */
        // Fornecedor cadastrado é o melhor rótulo disponível.
        $supplier = isset($data['supplier_id']) ? Supplier::whereKey($data['supplier_id'])->first() : null;

        if ($supplier) {
            return $supplier->display_name;
        }

        $counterpart = trim((string) ($data['counterpart'] ?? ''));

        if ($counterpart !== '') {
            return $counterpart;
        }

        return ($data['type'] ?? '') === Transaction::TYPE_RECEIVABLE ? 'Recebimento' : 'Pagamento';
    }

    public function update(TransactionRequest $request, Transaction $lancamento): RedirectResponse
    {
        $data = $request->validated();
        $data['description'] = $this->describe($data);
        unset($data['installments'], $data['repeat'], $data['interval'], $data['occurrences']);

        $scope = $this->scope($request);

        $lancamento->update($data);

        if ($scope === Transaction::SCOPE_ONE) {
            return back()->with('success', 'Lançamento atualizado.');
        }

        /*
         * Nas irmãs, só o que é da série inteira.
         *
         * Vencimento, baixa e numeração são de cada conta: propagar o
         * vencimento colocaria as doze parcelas no mesmo dia, e propagar a baixa
         * daria por paga uma conta que ninguém pagou.
         */
        $shared = collect($data)->only([
            'type', 'description', 'amount', 'cost_center_id', 'finance_category_id',
            'client_id', 'counterpart', 'supplier_id', 'payment_method_id', 'notes',
        ])->all();

        $others = $lancamento->inScope($scope)->whereKeyNot($lancamento->id)->update($shared);

        return back()->with(
            'success',
            $others === 0
                ? 'Lançamento atualizado.'
                : 'Atualizados este e mais '.$others.($others === 1 ? ' lançamento.' : ' lançamentos.')
        );
    }

    /**
     * Dar e desfazer baixa — é o que o seletor inline da listagem chama.
     */
    public function updateStatus(Request $request, Transaction $lancamento): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([Transaction::STATUS_PAID, Transaction::STATUS_PENDING])],
            'paid_at' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validated['status'] === Transaction::STATUS_PAID) {
            $lancamento->settle(
                isset($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : null,
                isset($validated['paid_amount']) ? (float) $validated['paid_amount'] : null,
            );
        } else {
            $lancamento->reopen();
        }

        return back();
    }

    public function destroy(Request $request, Transaction $lancamento): RedirectResponse
    {
        $scope = $this->scope($request);
        $removed = $lancamento->inScope($scope)->delete();

        return back()->with(
            'success',
            $removed === 1 ? 'Lançamento excluído.' : "{$removed} lançamentos excluídos."
        );
    }

    /**
     * Alcance pedido pelo modal. Padrão conservador: só esta conta.
     */
    private function scope(Request $request): string
    {
        $scope = $request->string('scope')->toString();

        return in_array($scope, Transaction::SCOPES, true) ? $scope : Transaction::SCOPE_ONE;
    }

    /**
     * Dois trios espelhados, um por direção do dinheiro: total, realizado e o
     * que ainda pesa. Os números seguem o mesmo recorte de período da listagem,
     * para o card e a lista sempre fecharem entre si.
     *
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function summary(array $filters): array
    {
        $month = $filters['month'];

        $slice = fn (string $type, ?string $status = null) => Transaction::query()
            ->ofType($type)
            ->when($month, fn ($query) => $query->inMonth($month))
            ->when($status, fn ($query) => $query->withStatus($status));

        // Realizado usa o valor efetivamente baixado, que pode ter juros ou desconto.
        $settled = fn (string $type) => $slice($type, Transaction::STATUS_PAID);

        // O total pode vir de uma coluna ou de uma expressão (valor baixado).
        $card = fn ($query, string|Expression $column = 'amount') => [
            'amount' => round((float) (clone $query)->sum($column), 2),
            'count' => (clone $query)->count(),
        ];

        $settledSum = DB::raw('coalesce(paid_amount, amount)');

        return [
            'month' => $month,
            'payable' => [
                'total' => $card($slice(Transaction::TYPE_PAYABLE)),
                'paid' => $card($settled(Transaction::TYPE_PAYABLE), $settledSum),
                'overdue' => $card($slice(Transaction::TYPE_PAYABLE, Transaction::STATUS_OVERDUE)),
            ],
            'receivable' => [
                'total' => $card($slice(Transaction::TYPE_RECEIVABLE)),
                'paid' => $card($settled(Transaction::TYPE_RECEIVABLE), $settledSum),
                'open' => $card($slice(Transaction::TYPE_RECEIVABLE, Transaction::STATUS_OPEN)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'description' => $transaction->description,
            'amount' => (float) $transaction->amount,
            'due_date' => $transaction->due_date?->format('Y-m-d'),
            'due_date_label' => $transaction->due_date?->format('d/m/Y'),
            'paid_at' => $transaction->paid_at?->format('Y-m-d'),
            'paid_at_label' => $transaction->paid_at?->format('d/m/Y'),
            'paid_amount' => $transaction->paid_amount === null ? null : (float) $transaction->paid_amount,
            'status' => $transaction->status(),
            'days_left' => $transaction->daysLeft(),

            'cost_center_id' => $transaction->cost_center_id,
            'finance_category_id' => $transaction->finance_category_id,
            'client_id' => $transaction->client_id,

            'cost_center' => $transaction->costCenter
                ? ['id' => $transaction->costCenter->id, 'name' => $transaction->costCenter->name, 'color' => $transaction->costCenter->color]
                : null,
            'category' => $transaction->category
                ? ['id' => $transaction->category->id, 'name' => $transaction->category->name, 'color' => $transaction->category->color]
                : null,
            'client' => $transaction->client ? ['id' => $transaction->client->id, 'name' => $transaction->client->display_name] : null,

            // Como a conta nasceu: avulsa, parcela de uma dívida, ou cobrança
            // de um contrato que se renova.
            'kind' => match (true) {
                $transaction->recurrence_id !== null => 'recurring',
                $transaction->series_id !== null => 'installments',
                default => 'once',
            },

            // Cadastrado tem prioridade; o texto antigo ainda serve de rótulo.
            'counterpart' => $transaction->supplier?->display_name ?? $transaction->counterpart,
            'supplier_id' => $transaction->supplier_id,
            'payment_method_id' => $transaction->payment_method_id,
            // Cadastrada tem prioridade; o texto antigo ainda serve de rótulo
            // para o que foi digitado antes do cadastro existir.
            'payment_method' => $transaction->paymentMethod?->name ?? $transaction->payment_method,
            'notes' => $transaction->notes,
            'installment' => $transaction->installment,
            'installments' => $transaction->installments,
            'series_id' => $transaction->series_id,
        ];
    }
}
