<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Support\ListSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
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

        $query = PaymentMethod::query()->withCount('transactions');

        ListSorting::apply($query, self::SORTS, $sorting['sort'], $sorting['direction']);

        return Inertia::render('configuracoes/formas-de-pagamento', [
            'filters' => $sorting,
            'paymentMethods' => $query->get()->map(fn (PaymentMethod $method) => [
                'id' => $method->id,
                'name' => $method->name,
                'description' => $method->description,
                'color' => $method->color,
                'active' => $method->active,
                'transactions_count' => $method->transactions_count,
            ]),
            'colors' => PaymentMethod::COLORS,
        ]);
    }

    /**
     * Ativas primeiro; dentro de cada grupo, ordem alfabética.
     *
     * Precisa ser pública: ListSorting chama de fora, e is_callable() recusa
     * método privado visto de outro escopo.
     *
     * @param  Builder<PaymentMethod>  $query
     */
    public static function orderByStatus($query, string $direction): void
    {
        $query->orderBy('active', $direction === 'asc' ? 'desc' : 'asc')->orderBy('name');
    }

    public function store(Request $request): RedirectResponse
    {
        PaymentMethod::create($this->validated($request));

        return back()->with('success', 'Forma de pagamento criada.');
    }

    public function update(Request $request, PaymentMethod $forma): RedirectResponse
    {
        $forma->update($this->validated($request, $forma));

        return back()->with('success', 'Forma de pagamento atualizada.');
    }

    /**
     * Excluir não pode apagar histórico: os lançamentos ficam sem forma de
     * pagamento. Com movimento, desativar costuma ser o que a pessoa quer.
     */
    public function destroy(PaymentMethod $forma): RedirectResponse
    {
        $name = $forma->name;

        $forma->delete();

        return back()->with('success', "Forma de pagamento {$name} excluída.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PaymentMethod $method = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('payment_methods', 'name')->ignore($method)],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(PaymentMethod::COLORS)],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Já existe uma forma de pagamento com esse nome.',
        ], [
            'name' => 'nome',
            'description' => 'descrição',
            'color' => 'cor',
        ]);
    }
}
