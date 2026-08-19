<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Asaas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A conexão com o Asaas, usada para conciliar as cobranças.
 */
class AsaasController extends Controller
{
    public function index(): Response
    {
        $config = Asaas::config();

        return Inertia::render('configuracoes/asaas', [
            'settings' => [
                // A chave nunca volta para a tela: só se sabe que existe.
                'has_key' => filled($config['api_key']),
                'environment' => $config['environment'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:255'],
            'environment' => ['required', Rule::in(['production', 'sandbox'])],
        ], [], ['api_key' => 'chave de API', 'environment' => 'ambiente']);

        $save = ['environment' => $data['environment']];

        // Campo de chave em branco significa "não mexi", e não "apague".
        if (filled($data['api_key'])) {
            $save['api_key'] = trim($data['api_key']);
        }

        Asaas::save($save);

        return back()->with('success', 'Conexão com o Asaas salva.');
    }

    public function test(): RedirectResponse
    {
        $result = Asaas::test();

        return back()->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }
}
