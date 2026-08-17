<?php

namespace App\Support;

/**
 * CPF e CNPJ escritos como se lê.
 *
 * O banco guarda o que a pessoa digitou; aqui o formato é normalizado a partir
 * dos dígitos, então tanto faz se veio mascarado ou não.
 */
final class Documents
{
    public static function format(?string $document): string
    {
        $digits = preg_replace('/\D/', '', (string) $document) ?? '';

        return match (strlen($digits)) {
            11 => self::apply('###.###.###-##', $digits),
            14 => self::apply('##.###.###/####-##', $digits),
            // Nem CPF nem CNPJ: devolve como está, em vez de inventar formato.
            default => (string) $document,
        };
    }

    public static function isCompany(?string $document): bool
    {
        return strlen(preg_replace('/\D/', '', (string) $document) ?? '') === 14;
    }

    private static function apply(string $mask, string $digits): string
    {
        $out = '';
        $index = 0;

        foreach (str_split($mask) as $char) {
            $out .= $char === '#' ? $digits[$index++] : $char;
        }

        return $out;
    }
}
