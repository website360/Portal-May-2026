<?php

namespace App\Support;

/**
 * A marcação mínima que um contrato precisa, convertida para HTML.
 *
 * Não é Markdown completo de propósito: quem escreve contrato aqui precisa de
 * título, cláusula em negrito, lista e parágrafo — e nada além disso. Um editor
 * rico traria colar-do-Word, estilos embutidos e PDF imprevisível.
 *
 *   # Título          §  ## Subtítulo
 *   **negrito**       §  *itálico*
 *   - item da lista   §  1. item numerado
 *   ---               §  linha divisória
 */
final class ContractMarkup
{
    public static function toHtml(?string $text): string
    {
        if (blank($text)) {
            return '';
        }

        $html = [];
        $list = null;
        $table = null;

        foreach (preg_split('/\R/', $text) ?: [] as $raw) {
            $line = rtrim($raw);
            $trimmed = trim($line);

            /*
             * Tabela em canos, como o cronograma de etapas do contrato:
             *
             *   | Módulo | Ação             | Prazo   |
             *   | --- | --- | --- |
             *   | 01     | Criação do layout | 10 dias |
             *
             * A segunda linha é o separador e não vira conteúdo; ela só marca
             * que a primeira era cabeçalho.
             */
            $isRow = str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|') && strlen($trimmed) > 1;

            if ($isRow) {
                $cells = array_map('trim', explode('|', trim($trimmed, '|')));

                // Linha de separador: "| --- | --- |".
                if (array_reduce($cells, fn ($all, $c) => $all && preg_match('/^:?-{2,}:?$/', $c) === 1, true)) {
                    continue;
                }

                if ($table === null) {
                    if ($list !== null) {
                        $html[] = "</{$list}>";
                        $list = null;
                    }

                    $html[] = '<table>';
                    $table = 'head';
                }

                $tag = $table === 'head' ? 'th' : 'td';
                $html[] = '<tr><'.$tag.'>'.implode("</{$tag}><{$tag}>", array_map(self::inline(...), $cells)).'</'.$tag.'></tr>';
                $table = 'body';

                continue;
            }

            if ($table !== null) {
                $html[] = '</table>';
                $table = null;
            }

            // Uma linha de lista abre a lista; qualquer outra coisa fecha.
            $bullet = preg_match('/^[-*]\s+(.*)$/', $trimmed, $m);
            $number = preg_match('/^\d+[.)]\s+(.*)$/', $trimmed, $n);
            $wanted = $bullet ? 'ul' : ($number ? 'ol' : null);

            if ($list !== null && $wanted !== $list) {
                $html[] = "</{$list}>";
                $list = null;
            }

            if ($wanted !== null) {
                if ($list === null) {
                    $html[] = "<{$wanted}>";
                    $list = $wanted;
                }

                $html[] = '<li>'.self::inline($bullet ? $m[1] : $n[1]).'</li>';

                continue;
            }

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.*)$/', $trimmed, $h)) {
                $level = strlen($h[1]) + 1; // # vira h2: o h1 é o título do documento.
                $html[] = "<h{$level}>".self::inline($h[2])."</h{$level}>";

                continue;
            }

            if (preg_match('/^-{3,}$/', $trimmed)) {
                $html[] = '<hr>';

                continue;
            }

            $html[] = '<p>'.self::inline($trimmed).'</p>';
        }

        if ($list !== null) {
            $html[] = "</{$list}>";
        }

        if ($table !== null) {
            $html[] = '</table>';
        }

        return implode("\n", $html);
    }

    /**
     * Negrito e itálico.
     *
     * O escape vem antes da marcação: assim um "<" digitado no contrato aparece
     * como "<", e não vira etiqueta HTML dentro do PDF.
     */
    public static function inline(string $text): string
    {
        $safe = e($text);

        $safe = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe) ?? $safe;

        return preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/s', '<em>$1</em>', $safe) ?? $safe;
    }
}
