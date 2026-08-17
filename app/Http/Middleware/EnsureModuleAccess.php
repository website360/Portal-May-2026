<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Barra o acesso a um módulo quando o usuário não tem o nível necessário.
 *
 * Roda na pilha web inteira, e não rota a rota, de propósito: assim um módulo
 * novo nasce protegido mesmo que quem o escreveu não conheça esta classe. Rotas
 * que não pertencem a módulo nenhum — login, perfil, sair — passam direto.
 */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $module = Permissions::moduleFor($request->route()?->getName());

        if ($user === null || $module === null) {
            return $next($request);
        }

        $required = Permissions::levelFor($request->method());

        if ($user->allows($module, $required)) {
            return $next($request);
        }

        /*
         * Sem leitura, a página não existe para essa pessoa: 403 seco.
         * Com leitura mas sem escrita, a tentativa de gravar volta para a
         * própria tela com a explicação, em vez de trocar a página por um erro.
         */
        if ($required === Permissions::WRITE && $user->allows($module, Permissions::READ)) {
            return back()->withErrors([
                'permissao' => 'Você tem acesso somente de leitura em '.Permissions::MODULES[$module].'.',
            ]);
        }

        abort(403, 'Você não tem acesso a '.Permissions::MODULES[$module].'.');
    }
}
