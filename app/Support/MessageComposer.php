<?php

namespace App\Support;

use App\Models\MessageTemplate;

/**
 * Monta a mensagem que vai sair.
 *
 * Três coisas acontecem aqui, nesta ordem: escolher o modelo cujas regras
 * batem com o caso, sortear uma das variações dele, e trocar os marcadores
 * pelos valores de verdade. Sem modelo cadastrado, sai o texto embutido no
 * código — o sistema nunca fica mudo por falta de configuração.
 */
final class MessageComposer
{
    /**
     * Um trecho que só aparece se os marcadores dentro dele tiverem valor.
     *
     * Serve para o "_{{manutencao.observacoes}}_" não virar dois sublinhados
     * soltos quando não houve observação nenhuma.
     */
    private const OPTIONAL = '/\[\[(.*?)\]\]/s';

    /**
     * @param  array<string, string>  $variables  o que cada marcador vale
     * @param  array<string, mixed>  $facts  o que as regras podem perguntar
     * @return array{text: string, template: ?MessageTemplate}
     */
    public static function compose(string $trigger, array $variables, array $facts, string $fallback): array
    {
        $template = self::choose($trigger, $facts);

        if ($template === null) {
            return ['text' => $fallback, 'template' => null];
        }

        return ['text' => self::render($template->variation(), $variables), 'template' => $template];
    }

    /**
     * O primeiro modelo ativo cujas regras passam.
     *
     * A ordem é a prioridade, da maior para a menor: quem escreveu uma regra
     * específica — "quando o cliente é a Padaria" — espera que ela ganhe do
     * modelo geral, e não que o desempate seja a data de cadastro.
     *
     * @param  array<string, mixed>  $facts
     */
    public static function choose(string $trigger, array $facts): ?MessageTemplate
    {
        return MessageTemplate::query()
            ->where('trigger', $trigger)
            ->where('active', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->first(fn (MessageTemplate $template) => MessageRules::passes($template->conditions ?? [], $facts));
    }

    /**
     * Troca os marcadores e limpa o que sobrou.
     *
     * Marcador desconhecido vira vazio em vez de aparecer cru: o editor já
     * recusa salvar um texto com marcador que o gatilho não conhece, então
     * chegar aqui é sinal de catálogo mudado depois — e "{{cliente.x}}" no
     * WhatsApp do cliente seria pior do que um espaço a menos.
     *
     * @param  array<string, string>  $variables
     */
    public static function render(string $body, array $variables): string
    {
        $texto = preg_replace_callback(self::OPTIONAL, function (array $match) use ($variables) {
            $marcadores = self::markersIn($match[1]);

            /*
             * O bloco cai quando todos os marcadores dele estão vazios — e não
             * quando o texto renderizado fica vazio. É o que faz "[[Obs:
             * {{observacoes}}]]" sumir inteiro em vez de deixar um "Obs:"
             * órfão. Bloco sem marcador nenhum fica: quem escreveu quis os
             * colchetes ali.
             */
            $temValor = array_filter($marcadores, fn (string $chave) => filled($variables[$chave] ?? null));

            return $marcadores !== [] && $temValor === [] ? '' : self::replace($match[1], $variables);
        }, $body);

        $texto = self::replace($texto, $variables);

        // Linha que ficou vazia porque a variável dela estava vazia não deve
        // abrir um buraco no meio da mensagem.
        $texto = preg_replace("/\n{3,}/", "\n\n", $texto);

        return trim($texto);
    }

    /**
     * Os marcadores escritos num trecho, na ordem em que aparecem.
     *
     * @return list<string>
     */
    private static function markersIn(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', $text, $matches);

        return array_values($matches[1] ?? []);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private static function replace(string $text, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            fn (array $match) => $variables[$match[1]] ?? '',
            $text
        );
    }
}
