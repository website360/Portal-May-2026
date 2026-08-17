<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\FinanceCategory;
use App\Support\ListSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FinanceCategoryController extends Controller
{
    /**
     * A tela ja separa despesa de receita em duas colunas, entao ordenar por
     * tipo nao teria efeito visivel — as opcoes aqui valem dentro de cada grupo.
     *
     * @var array<string, string|callable>
     */
    private const SORTS = [
        'name' => 'name',
        'usage' => 'transactions_count',
        'status' => [self::class, 'orderByStatus'],
    ];

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'name');

        $query = FinanceCategory::query()->withCount('transactions');

        ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']);

        return Inertia::render('configuracoes/categorias', [
            'filters' => $sorting,
            'categories' => $query
                ->get()
                ->map(fn (FinanceCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'color' => $category->color,
                    'active' => $category->active,
                    'transactions_count' => $category->transactions_count,
                ]),
            'colors' => CostCenter::COLORS,
        ]);
    }

    /**
     * Ativas primeiro; dentro de cada grupo, ordem alfabética — sem isso as
     * inativas ficariam intercaladas de forma imprevisível.
     *
     * Precisa ser pública: ListSorting chama de fora, e is_callable() recusa
     * método privado visto de outro escopo.
     *
     * @param  Builder<FinanceCategory>  $query
     */
    public static function orderByStatus($query, string $direction): void
    {
        $query->orderBy('active', $direction === 'asc' ? 'desc' : 'asc')->orderBy('name');
    }

    public function store(Request $request): RedirectResponse
    {
        FinanceCategory::create($this->validated($request));

        return back()->with('success', 'Categoria criada.');
    }

    public function update(Request $request, FinanceCategory $categoria): RedirectResponse
    {
        $categoria->update($this->validated($request, $categoria));

        return back()->with('success', 'Categoria atualizada.');
    }

    public function destroy(FinanceCategory $categoria): RedirectResponse
    {
        $name = $categoria->name;

        $categoria->delete();

        return back()->with('success', "Categoria {$name} excluída.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?FinanceCategory $category = null): array
    {
        return $request->validate([
            // Nome repetido só incomoda dentro da mesma natureza: pode existir
            // "Serviços" como receita e como despesa.
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('finance_categories', 'name')
                    ->where(fn ($query) => $query->where('type', $request->input('type')))
                    ->ignore($category),
            ],
            'type' => ['required', Rule::in(FinanceCategory::TYPES)],
            'color' => ['required', Rule::in(CostCenter::COLORS)],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Já existe uma categoria com esse nome nessa natureza.',
        ]);
    }
}
