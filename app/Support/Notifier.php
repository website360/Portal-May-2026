<?php

namespace App\Support;

use App\Models\MessageTemplate;
use App\Models\WhatsappConnection;

/**
 * Manda o aviso: escolhe o modelo, monta o texto e entrega por cada canal.
 *
 * Nunca lança. O trabalho que gerou o aviso — uma manutenção registrada, por
 * exemplo — já aconteceu, e um servidor de e-mail fora do ar não pode desfazer
 * o que foi feito. O que houve volta na resposta, para a tela contar.
 */
final class Notifier
{
    /**
     * @param  array<string, string>  $variables  o que cada marcador vale
     * @param  array<string, mixed>  $facts  o que as regras podem perguntar
     * @param  array<string, list<array{name?: string, phone?: string, email?: string}>>  $audience
     *                                                                                              quem é o cliente, quem são os administradores, quem executou
     * @return array{ok: bool, message: string, sent: list<string>, errors: list<string>}
     */
    public static function dispatch(string $trigger, array $variables, array $facts, array $audience, string $fallback): array
    {
        $escolha = MessageComposer::compose($trigger, $variables, $facts, $fallback);
        $template = $escolha['template'];

        // Sem modelo cadastrado o comportamento é o de antes: WhatsApp para o cliente.
        $canais = $template?->channels ?: [MessageDelivery::WHATSAPP];
        $grupos = $template?->recipients ?: [MessageDelivery::CLIENT];

        $assunto = filled($template?->subject)
            ? MessageComposer::render($template->subject, $variables)
            : MessageTriggers::labelFor($trigger);

        $pessoas = self::people($grupos, $audience);
        $enviados = [];
        $erros = [];
        $pulados = [];

        foreach ($canais as $canal) {
            foreach ($pessoas as $pessoa) {
                $contato = $canal === MessageDelivery::EMAIL ? 'email' : 'phone';

                /*
                 * Quem não tem o contato daquele canal é pulado, mas o motivo
                 * fica guardado: "não enviado" sem dizer por quê obriga quem
                 * usa a abrir o cadastro do cliente para descobrir.
                 */
                if (blank($pessoa[$contato] ?? null)) {
                    $pulados[] = MessageDelivery::RECIPIENTS[$pessoa['kind']].' não tem '
                        .($contato === 'email' ? 'e-mail' : 'telefone').' cadastrado.';

                    continue;
                }

                $resultado = match ($canal) {
                    MessageDelivery::WHATSAPP => self::whatsapp($pessoa, $escolha['text']),
                    MessageDelivery::EMAIL => Smtp::send($pessoa['email'], $assunto, $escolha['text']),
                    default => ['ok' => false, 'message' => "Canal desconhecido: {$canal}."],
                };

                $resultado['ok']
                    ? $enviados[] = MessageDelivery::CHANNELS[$canal]
                    : $erros[] = $resultado['message'];
            }
        }

        return self::result($enviados, $erros, $pulados);
    }

    /**
     * As pessoas de verdade por trás dos grupos escolhidos.
     *
     * Sem repetição: o administrador que também é quem executou recebe uma vez,
     * e não duas.
     *
     * @param  list<string>  $grupos
     * @param  array<string, list<array{name?: string, phone?: string, email?: string}>>  $audience
     * @return list<array{kind: string, name?: string, phone?: string, email?: string}>
     */
    private static function people(array $grupos, array $audience): array
    {
        $pessoas = [];

        foreach ($grupos as $grupo) {
            foreach ($audience[$grupo] ?? [] as $pessoa) {
                // A mesma pessoa em dois grupos recebe uma vez, e não duas.
                $pessoas[($pessoa['email'] ?? '').'|'.($pessoa['phone'] ?? '')] ??= $pessoa + ['kind' => $grupo];
            }
        }

        return array_values($pessoas);
    }

    /**
     * @param  array{phone?: string}  $pessoa
     * @return array{ok: bool, message: string}
     */
    private static function whatsapp(array $pessoa, string $texto): array
    {
        $connection = WhatsappConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return ['ok' => false, 'message' => 'O WhatsApp não está conectado. Conecte em Configurações › WhatsApp e reenvie.'];
        }

        return (new Evolution($connection))->sendText($pessoa['phone'], $texto);
    }

    /**
     * @param  list<string>  $enviados
     * @param  list<string>  $erros
     * @param  list<string>  $pulados
     * @return array{ok: bool, message: string, sent: list<string>, errors: list<string>}
     */
    private static function result(array $enviados, array $erros, array $pulados): array
    {
        $canais = array_values(array_unique($enviados));
        $ok = $canais !== [] && $erros === [];

        // Os pulados só interessam quando nada saiu: com o aviso entregue, quem
        // não tinha telefone é detalhe, e não notícia.
        $motivos = $erros !== [] ? $erros : array_values(array_unique($pulados));

        return [
            'ok' => $ok,
            'sent' => $canais,
            'errors' => $erros,
            'message' => match (true) {
                $ok => 'Aviso enviado por '.self::andJoin($canais).'.',
                $canais !== [] => 'Enviado por '.self::andJoin($canais).', mas '.implode(' ', $motivos),
                $motivos !== [] => implode(' ', $motivos),
                default => 'Nada foi enviado: o modelo não tem destinatário.',
            },
        ];
    }

    /** @param  list<string>  $itens */
    private static function andJoin(array $itens): string
    {
        if (count($itens) < 2) {
            return implode('', $itens);
        }

        return implode(', ', array_slice($itens, 0, -1)).' e '.end($itens);
    }
}
