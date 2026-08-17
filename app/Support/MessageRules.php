<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * As regras que decidem se um modelo serve para o caso da vez.
 *
 * Uma condição é sempre a mesma forma: um campo do gatilho, um operador e um
 * valor. Todas precisam passar — "E", nunca "ou". Quem quer alternativa escreve
 * dois modelos, e é mais fácil de ler na tela do que uma árvore de condições.
 */
final class MessageRules
{
    /** @var array<string, string> */
    public const OPERATORS = [
        'igual' => 'é',
        'diferente' => 'não é',
        'contem' => 'contém',
        'nao_contem' => 'não contém',
        'maior' => 'é maior que',
        'menor' => 'é menor que',
        'preenchido' => 'está preenchido',
        'vazio' => 'está vazio',
    ];

    /** Operadores que não pedem valor: o campo em si já é a pergunta. */
    public const WITHOUT_VALUE = ['preenchido', 'vazio'];

    /**
     * @param  list<array{field: string, operator: string, value?: string|null}>  $conditions
     * @param  array<string, mixed>  $facts
     */
    public static function passes(array $conditions, array $facts): bool
    {
        foreach ($conditions as $condition) {
            if (! self::check($condition, $facts[$condition['field'] ?? ''] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{field: string, operator: string, value?: string|null}  $condition
     */
    private static function check(array $condition, mixed $fact): bool
    {
        $valor = $condition['value'] ?? null;

        return match ($condition['operator'] ?? '') {
            'preenchido' => self::filled($fact),
            'vazio' => ! self::filled($fact),
            'igual' => self::equals($fact, $valor),
            'diferente' => ! self::equals($fact, $valor),
            'contem' => str_contains(self::text($fact), self::text($valor)),
            'nao_contem' => ! str_contains(self::text($fact), self::text($valor)),
            'maior' => self::number($fact) > self::number($valor),
            'menor' => self::number($fact) < self::number($valor),
            // Operador que não existe não pode deixar passar calado: seria uma
            // regra escrita que nunca barra nada.
            default => false,
        };
    }

    /**
     * Igualdade como gente compara.
     *
     * Números batem por valor (2 é igual a "2.0"), booleanos entendem "sim" e
     * "não", e texto ignora caixa, acento e espaço nas pontas — quem escreve a
     * regra digitou o nome do cliente de memória, não copiou do cadastro.
     */
    private static function equals(mixed $fact, mixed $value): bool
    {
        if (is_bool($fact)) {
            return $fact === in_array(self::text($value), ['sim', 'true', '1'], true);
        }

        if (is_numeric($fact) && is_numeric($value)) {
            return (float) $fact === (float) $value;
        }

        return self::text($fact) === self::text($value);
    }

    private static function filled(mixed $fact): bool
    {
        if (is_bool($fact)) {
            return $fact;
        }

        return is_numeric($fact) ? (float) $fact !== 0.0 : filled($fact);
    }

    /** Minúsculas, sem acento e sem espaço sobrando — os dois lados iguais. */
    private static function text(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? 'sim' : 'nao';
        }

        return Str::lower(Str::ascii(trim((string) $value)));
    }

    private static function number(mixed $value): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    /**
     * A regra em uma frase, para a lista de modelos.
     *
     * @param  list<array{field: string, operator: string, value?: string|null}>  $conditions
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    public static function describe(array $conditions, array $labels): array
    {
        return array_map(function (array $condition) use ($labels) {
            $campo = $labels[$condition['field'] ?? ''] ?? ($condition['field'] ?? '?');
            $operador = self::OPERATORS[$condition['operator'] ?? ''] ?? '?';

            return in_array($condition['operator'] ?? '', self::WITHOUT_VALUE, true)
                ? "{$campo} {$operador}"
                : trim("{$campo} {$operador} ".($condition['value'] ?? ''));
        }, $conditions);
    }
}
