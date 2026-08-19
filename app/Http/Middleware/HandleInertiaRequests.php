<?php

namespace App\Http\Middleware;

use App\Models\WhatsappConnection;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'brand' => \App\Support\Brand::shared(),
            'auth' => [
                'user' => $request->user(),
                /*
                 * Mapa resolvido — administrador já chega com tudo. Serve para a
                 * tela esconder o que a pessoa não pode ver; quem barra de
                 * verdade é o EnsureModuleAccess, no servidor.
                 */
                'permissions' => $request->user()?->permissionMap() ?? [],
                'isAdmin' => (bool) $request->user()?->isAdmin(),
            ],
            /*
             * Viram toast no cliente. Aviso é o meio-termo entre sucesso e erro:
             * a ação aconteceu, mas uma parte dela não — registrar a manutenção
             * deu certo, mandar o relatório não. Erro trocaria a tela; sucesso
             * esconderia o que ficou faltando.
             */
            'flash' => [
                'success' => $request->session()->get('success'),
                'warning' => $request->session()->get('warning'),
            ],
            'whatsapp' => $this->whatsapp($request),
        ];
    }

    /**
     * A situação do WhatsApp para o rodapé do menu.
     *
     * Vai o que está gravado, nunca uma consulta ao servidor externo: isto roda
     * em toda requisição do sistema, e falar com a Evolution aqui faria cada
     * página esperar por um servidor de terceiro — inclusive quando ele estiver
     * fora. Quem atualiza é a própria tela, em segundo plano, quando o dado
     * está velho.
     *
     * @return array<string, mixed>|null
     */
    private function whatsapp(Request $request): ?array
    {
        // Só quem pode arrumar precisa ver: a tela de configuração é do admin.
        if (! $request->user()?->isAdmin()) {
            return null;
        }

        $connection = WhatsappConnection::current();

        if ($connection === null) {
            return ['configured' => false, 'status' => WhatsappConnection::STATUS_DISCONNECTED, 'checked_at' => null, 'stale' => true];
        }

        return [
            'configured' => true,
            'status' => $connection->status,
            'checked_at' => $connection->checked_at?->diffForHumans(),
            // Velho demais para servir de resposta: a tela vai conferir sozinha.
            'stale' => $connection->checked_at === null || $connection->checked_at->lt(now()->subMinutes(10)),
        ];
    }
}

