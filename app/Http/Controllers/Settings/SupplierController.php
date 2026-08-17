<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\ListSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
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

        $query = Supplier::query()->withCount('transactions');

        ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']);

        return Inertia::render('configuracoes/fornecedores', [
            'filters' => $sorting,
            'suppliers' => $query->get()->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'trade_name' => $supplier->trade_name,
                'document' => $supplier->document,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'active' => $supplier->active,
                'transactions_count' => $supplier->transactions_count,
            ]),
        ]);
    }

    /**
     * Precisa ser pública: ListSorting chama de fora, e is_callable() recusa
     * método privado visto de outro escopo.
     *
     * @param  Builder<Supplier>  $query
     */
    public static function orderByStatus($query, string $direction): void
    {
        $query->orderBy('active', $direction === 'asc' ? 'desc' : 'asc')->orderBy('name');
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request));

        return back()->with('success', 'Fornecedor criado.');
    }

    public function update(Request $request, Supplier $fornecedor): RedirectResponse
    {
        $fornecedor->update($this->validated($request, $fornecedor));

        return back()->with('success', 'Fornecedor atualizado.');
    }

    /**
     * Excluir não apaga histórico: os lançamentos ficam sem fornecedor, mas o
     * texto antigo continua servindo de rótulo.
     */
    public function destroy(Supplier $fornecedor): RedirectResponse
    {
        $name = $fornecedor->name;

        $fornecedor->delete();

        return back()->with('success', "Fornecedor {$name} excluído.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'document' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Já existe um fornecedor com esse nome.',
        ], [
            'name' => 'razão social',
            'trade_name' => 'nome fantasia',
            'document' => 'documento',
            'email' => 'e-mail',
            'phone' => 'telefone',
        ]);
    }
}
