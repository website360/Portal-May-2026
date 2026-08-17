<?php

namespace App\Http\Middleware;

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
        ];
    }
}
