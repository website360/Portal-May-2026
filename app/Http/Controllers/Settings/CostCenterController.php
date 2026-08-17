<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Support\ListSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CostCenterController extends Controller
{
    /** @var array<string, string|callable> */
    private const SORTS = [
        'name' => 'name',
        'usage' => 'transactions_count',
        'status' => [self::class, 'orderByStatus'],
    ];

    public function index(Request $request): Response
    {
        $sorting = ListSorting::resolve($request, self::SORTS, 'name');

        $query = CostCenter::query()->withCount('transactions');

        ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']);

        return Inertia::render('configuracoes/centros-de-custo', [
            'filters' => $sorting,
            'costCenters' => $query
                ->get()
                ->map(fn (CostCenter $center) => [
                    'id' => $center->id,
                    'name' => $center->name,
                    'description' => $center->description,
                    'color' => $center->color,
                    'active' => $center->active,
                    'transactions_count' => $center->transactions_count,
                ]),
            'colors' => CostCenter::COLORS,
        ]);
    }

    /**
     * Ativos primeiro; dentro de cada grupo, ordem alfabética — sem isso os
     * inativos ficariam intercalados de forma imprevisível.
     *
     * Precisa ser pública: ListSorting chama de fora, e is_callable() recusa
     * método privado visto de outro escopo.
     *
     * @param  Builder<CostCenter>  $query
     */
    public static function orderByStatus($query, string $direction): void
    {
        $query->orderBy('active', $direction === 'asc' ? 'desc' : 'asc')->orderBy('name');
    }

    public function store(Request $request): RedirectResponse
    {
        CostCenter::create($this->validated($request));

        return back()->with('success', 'Centro de custo criado.');
    }

    public function update(Request $request, CostCenter $centro): RedirectResponse
    {
        $centro->update($this->validated($request, $centro));

        return back()->with('success', 'Centro de custo atualizado.');
    }

    /**
     * Excluir não pode apagar histórico: lançamentos ficam sem centro de custo.
     * Quando já existe movimento, desativar costuma ser o que a pessoa quer.
     */
    public function destroy(CostCenter $centro): RedirectResponse
    {
        $name = $centro->name;

        $centro->delete();

        return back()->with('success', "Centro de custo {$name} excluído.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CostCenter $center = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('cost_centers', 'name')->ignore($center)],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(CostCenter::COLORS)],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Já existe um centro de custo com esse nome.',
        ]);
    }
}
