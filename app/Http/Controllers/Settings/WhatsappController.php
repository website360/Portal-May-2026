<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Support\Evolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappController extends Controller
{
    public function index(): Response
    {
        $connection = WhatsappConnection::current();

        return Inertia::render('configuracoes/whatsapp', [
            'connection' => $connection ? [
                'base_url' => $connection->base_url,
                'instance' => $connection->instance,
                // A chave nunca volta para a tela; só se ela existe.
                'has_key' => filled($connection->api_key),
                'status' => $connection->status,
                'number' => $connection->number,
                'checked_at' => $connection->checked_at?->format('d/m/Y H:i'),
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['required', 'url', 'max:255'],
            'instance' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+$/'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ], [
            'instance.regex' => 'Use apenas letras, números, ponto, hífen ou sublinhado.',
        ], [
            'base_url' => 'endereço do servidor',
            'instance' => 'nome da instância',
            'api_key' => 'chave de API',
        ]);

        $connection = WhatsappConnection::current() ?? new WhatsappConnection;

        /*
         * Trocar o servidor ou o nome da instância invalida o token guardado:
         * ele pertence a uma instância específica de um servidor específico.
         * Mantê-lo faria as chamadas seguintes falharem com 401 sem explicação.
         */
        if ($connection->exists && ($connection->base_url !== $data['base_url'] || $connection->instance !== $data['instance'])) {
            $connection->instance_token = null;
            $connection->instance_id = null;
        }

        $connection->base_url = $data['base_url'];
        $connection->instance = $data['instance'];

        // Chave em branco na edição significa "manter a que já está lá".
        if (filled($data['api_key'] ?? null)) {
            $connection->api_key = $data['api_key'];
        }

        if (blank($connection->api_key)) {
            return back()->withErrors(['api_key' => 'A chave de API é obrigatória na primeira configuração.']);
        }

        $connection->save();

        return back()->with('success', 'Conexão salva. Gere o QR Code para parear o aparelho.');
    }

    /**
     * Devolve o QR Code para leitura.
     *
     * Vai por JSON, e não por Inertia: a tela pede de novo a cada poucos
     * segundos enquanto espera o pareamento, e recarregar a página inteira a
     * cada tentativa seria desperdício.
     */
    public function qrCode(): JsonResponse
    {
        $connection = WhatsappConnection::current();

        if ($connection === null) {
            return response()->json(['ok' => false, 'qr' => null, 'message' => 'Configure o servidor antes.'], 422);
        }

        return response()->json((new Evolution($connection))->qrCode());
    }

    /** Consulta o estado e grava o que o servidor respondeu. */
    public function state(): JsonResponse
    {
        $connection = WhatsappConnection::current();

        if ($connection === null) {
            return response()->json(['ok' => false, 'status' => WhatsappConnection::STATUS_DISCONNECTED, 'message' => 'Nada configurado.'], 422);
        }

        $state = (new Evolution($connection))->state();

        if ($state['ok']) {
            $connection->update([
                'status' => $state['status'],
                'number' => $state['number'],
                'checked_at' => now(),
            ]);
        }

        return response()->json($state + ['checked_at' => $connection->fresh()->checked_at?->format('d/m/Y H:i')]);
    }

    public function disconnect(): RedirectResponse
    {
        $connection = WhatsappConnection::current();

        if ($connection === null) {
            return back();
        }

        $result = (new Evolution($connection))->logout();

        $connection->update(['status' => WhatsappConnection::STATUS_DISCONNECTED, 'number' => null]);

        return back()->with('success', $result['message']);
    }
}
