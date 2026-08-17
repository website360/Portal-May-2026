<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe uma rota a administradores.
 *
 * Existe separado do EnsureModuleAccess porque gerenciar usuários não é só mais
 * um nível de acesso a Configurações: quem abre essa tela pode se promover a
 * administrador. Tratar isso como permissão comum tornaria a permissão de
 * escrita em Configurações equivalente a acesso total ao sistema.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Só administradores gerenciam usuários.');

        return $next($request);
    }
}
