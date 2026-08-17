<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Ordenação de listagem vinda da URL.
 *
 * Cada listagem declara um mapa de chaves permitidas — nada que venha do
 * cliente vira nome de coluna sem passar por ele. A chave padrão costuma
 * apontar para a ordem "natural" da tela (mais urgente primeiro), que quase
 * sempre é multi-coluna e por isso é declarada como callable.
 */
final class ListSorting
{
    /**
     * @param  array<string, string|callable>  $allowed
     * @return array{sort: string, direction: string}
     */
    public static function resolve(Request $request, array $allowed, string $default, string $defaultDirection = 'asc'): array
    {
        $sort = $request->string('sort')->toString();
        $direction = strtolower($request->string('direction')->toString());
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : null;

        if (! array_key_exists($sort, $allowed)) {
            return ['sort' => $default, 'direction' => $direction ?? $defaultDirection];
        }

        return ['sort' => $sort, 'direction' => $direction ?? 'asc'];
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, string|callable>  $allowed
     */
    public static function apply($query, array $allowed, string $sort, string $direction): void
    {
        $target = $allowed[$sort] ?? null;

        if ($target === null) {
            return;
        }

        if (is_callable($target)) {
            $target($query, $direction);

            return;
        }

        /*
         * Um alvo declarado como [Classe::class, 'metodo'] que não é chamável
         * daqui — método privado, por exemplo — cairia no orderBy() abaixo
         * recebendo um array. Erra em silêncio e a ordenação some sem aviso.
         */
        if (! is_string($target)) {
            throw new InvalidArgumentException(
                "A ordenação '{$sort}' aponta para um callable inacessível. O método precisa ser público."
            );
        }

        $query->orderBy($target, $direction);
    }
}
