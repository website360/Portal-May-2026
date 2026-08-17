<?php

namespace App\Support;

final class Encoding
{
    /**
     * Caracteres que aparecem como "continuação" de uma sequência corrompida —
     * ou seja, como os bytes 0x80–0xBF são exibidos na tabela Windows-1252.
     * A faixa 0xA0–0xBF cai direto no suplemento Latin-1; o resto vira
     * pontuação tipográfica (aspas curvas, travessões, reticências).
     */
    private const TRAIL = '\x{0080}-\x{00BF}\x{20AC}\x{201A}\x{0192}\x{201E}\x{2026}'
        .'\x{2020}\x{2021}\x{02C6}\x{2030}\x{0160}\x{2039}\x{0152}\x{017D}'
        .'\x{2018}\x{2019}\x{201C}\x{201D}\x{2022}\x{2013}\x{2014}\x{02DC}'
        .'\x{2122}\x{0161}\x{203A}\x{0153}\x{017E}\x{0178}';

    /**
     * Conserta texto UTF-8 que foi lido como Windows-1252 e regravado como
     * UTF-8 — o que transforma "São" em "SÃo". Reconverter devolve os bytes
     * originais.
     *
     * Exportações de banco passam por isso com frequência, e o estrago é
     * silencioso: o arquivo continua sendo UTF-8 válido, só que errado.
     *
     * O conserto é feito sequência por sequência, e não na string inteira, de
     * propósito: quando a exportação perdeu bytes no meio do caminho, um
     * trecho irrecuperável não pode impedir o resto de ser recuperado.
     */
    public static function repairMojibake(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Sem um marcador clássico da corrupção, o texto já está correto e não
        // vale o risco de mexer nele.
        if (! preg_match('/[\x{00C2}\x{00C3}]['.self::TRAIL.']|\x{00E2}\x{20AC}/u', $value)) {
            return $value;
        }

        $repaired = preg_replace_callback(
            // Um caractere-líder de 2 bytes (Â–ß) seguido de uma continuação,
            // ou um de 3 bytes (à–ï) seguido de duas.
            '/[\x{00C2}-\x{00DF}]['.self::TRAIL.']|[\x{00E0}-\x{00EF}]['.self::TRAIL.']{2}/u',
            static function (array $match): string {
                $bytes = @mb_convert_encoding($match[0], 'Windows-1252', 'UTF-8');

                // Só troca se os bytes recuperados forem UTF-8 válido; se não
                // forem, aquele trecho não veio de corrupção (ou veio quebrado).
                return is_string($bytes) && mb_check_encoding($bytes, 'UTF-8')
                    ? $bytes
                    : $match[0];
            },
            $value
        );

        return is_string($repaired) ? $repaired : $value;
    }
}
