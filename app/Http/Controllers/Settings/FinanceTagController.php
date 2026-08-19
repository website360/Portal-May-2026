<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Support\FinanceTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * As etiquetas do financeiro — rótulos livres e múltiplos por lançamento, para
 * cruzar filtros e relatórios. Guardadas em arquivo (sem tabela).
 */
class FinanceTagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('configuracoes/etiquetas', [
            'tags' => FinanceTags::all(),
            'colors' => CostCenter::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        FinanceTags::create($data['name'], $data['color']);

        return back()->with('success', 'Etiqueta criada.');
    }

    public function update(Request $request, int $etiqueta): RedirectResponse
    {
        abort_unless(FinanceTags::exists($etiqueta), 404);

        $data = $this->validated($request);
        FinanceTags::update($etiqueta, $data['name'], $data['color']);

        return back()->with('success', 'Etiqueta atualizada.');
    }

    public function destroy(int $etiqueta): RedirectResponse
    {
        abort_unless(FinanceTags::exists($etiqueta), 404);

        FinanceTags::delete($etiqueta);

        return back()->with('success', 'Etiqueta excluída.');
    }

    /**
     * @return array{name: string, color: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', Rule::in(CostCenter::COLORS)],
        ], [], ['name' => 'nome', 'color' => 'cor']);
    }
}
