<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MailSetting;
use App\Support\Smtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * O servidor de e-mail da agência.
 *
 * Fica ao lado da conexão do WhatsApp: são as duas portas de saída do sistema,
 * e quem procura uma procura a outra no mesmo lugar.
 */
class MailController extends Controller
{
    public function index(): Response
    {
        $settings = MailSetting::current();

        return Inertia::render('configuracoes/email', [
            'settings' => $settings === null ? null : [
                'host' => $settings->host,
                'port' => $settings->port,
                'username' => $settings->username,
                // A senha nunca volta para a tela: só se sabe que existe.
                'has_password' => filled($settings->password),
                'encryption' => $settings->encryption,
                'from_address' => $settings->from_address,
                'from_name' => $settings->from_name,
                'active' => $settings->active,
                'tested_at' => $settings->tested_at?->format('d/m/Y H:i'),
                'test_error' => $settings->test_error,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'from_address' => ['required', 'email', 'max:190'],
            'from_name' => ['required', 'string', 'max:120'],
            'active' => ['boolean'],
        ], [], [
            'host' => 'servidor',
            'port' => 'porta',
            'username' => 'usuário',
            'password' => 'senha',
            'from_address' => 'endereço de envio',
            'from_name' => 'nome de quem envia',
        ]);

        $settings = MailSetting::current() ?? new MailSetting;

        // Campo de senha em branco significa "não mexi", e não "apague".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $settings->fill($data)->save();

        return back()->with('success', 'Configuração de e-mail salva.');
    }

    /**
     * Manda um e-mail de teste para quem está pedindo.
     *
     * Sem isto, descobrir que a senha estava errada só aconteceria no dia em
     * que um cliente deixasse de receber o relatório — e ninguém ficaria
     * sabendo.
     */
    public function test(Request $request): RedirectResponse
    {
        $settings = MailSetting::current();

        if ($settings === null) {
            return back()->with('error', 'Salve a configuração antes de testar.');
        }

        // Testa o que está salvo, mesmo que ainda não esteja ativo.
        Smtp::apply($settings->replicate()->forceFill(['active' => true]));

        $resultado = Smtp::send(
            $request->user()->email,
            'Teste do Sistema May',
            "Se você está lendo isto, o servidor de e-mail está funcionando.\n\nSistema May"
        );

        $settings->forceFill([
            'tested_at' => $resultado['ok'] ? now() : null,
            'test_error' => $resultado['ok'] ? null : $resultado['message'],
        ])->save();

        return back()->with(
            $resultado['ok'] ? 'success' : 'error',
            $resultado['ok'] ? "E-mail de teste enviado para {$request->user()->email}." : $resultado['message']
        );
    }
}
