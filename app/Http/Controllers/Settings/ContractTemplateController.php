<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Support\ContractPlaceholders;
use App\Support\ContractTypography;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Os modelos de contrato — um por serviço.
 *
 * Ficam nas configurações, junto com os outros cadastros de apoio: são a
 * matéria-prima do gerador, não o trabalho do dia a dia.
 */
class ContractTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('configuracoes/modelos-de-contrato', [
            'templates' => ContractTemplate::query()
                ->withCount('contracts')
                ->orderBy('name')
                ->get()
                ->map(fn (ContractTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'body' => $template->body,
                    'active' => $template->active,
                    'with_signatures' => $template->with_signatures,
                    'contracts_count' => $template->contracts_count,
                    'fields' => array_map(fn (string $key) => [
                        'key' => $key,
                        'label' => ContractPlaceholders::labelFor($key),
                    ], $template->customPlaceholders()),
                ])
                ->all(),
            // O catálogo vira a ajuda ao lado do editor.
            'catalog' => ContractPlaceholders::CATALOG,
            // A arte do PDF falhava calada; aqui a tela diz o que encontrou.
            'art' => (new ContractTypography)->status(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = ContractTemplate::create($this->validated($request));

        return back()->with('success', "Modelo {$template->name} criado.");
    }

    public function update(Request $request, ContractTemplate $modelo): RedirectResponse
    {
        $modelo->update($this->validated($request, $modelo));

        return back()->with('success', "Modelo {$modelo->name} atualizado.");
    }

    public function destroy(ContractTemplate $modelo): RedirectResponse
    {
        $name = $modelo->name;

        /*
         * Os contratos já gerados ficam: eles guardam o texto final, então
         * perder o modelo não os apaga nem os altera. A chave estrangeira vira
         * nula sozinha.
         */
        $modelo->delete();

        return back()->with('success', "Modelo {$name} excluído. Os contratos já gerados continuam.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ContractTemplate $template = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('contract_templates')->ignore($template)],
            'description' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:60000'],
            'active' => ['boolean'],
            'with_signatures' => ['boolean'],
        ], [
            'name.unique' => 'Já existe um modelo com esse nome.',
        ], [
            'name' => 'nome',
            'body' => 'texto do contrato',
        ]);
    }
}
