<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * O aviso que sai quando um ticket cai no colo de alguém.
 *
 * Como os outros avisos do sistema, nunca lança: a atribuição já foi salva, e
 * um servidor de e-mail fora do ar não pode desfazê-la. Por onde sai e para
 * quem é decisão do modelo em Configurações › Mensagens; sem nenhum cadastrado,
 * o padrão embutido manda um e-mail para o responsável.
 */
final class TicketAssignedAlert
{
    public function __construct(private readonly Ticket $ticket, private readonly User $assignee) {}

    /**
     * Envia, e devolve o que houve. O chamador decide se conta na tela.
     *
     * @return array{ok: bool, message: string, sent: list<string>, errors: list<string>}
     */
    public function send(): array
    {
        return Notifier::dispatch(
            MessageTriggers::TICKET_ASSIGNED,
            $this->variables(),
            $this->facts(),
            $this->audience(),
            MessageComposer::render(self::defaultBody(), $this->variables()),
        );
    }

    /**
     * Quem recebe: o responsável, no grupo "quem executou".
     *
     * @return array<string, list<array{name?: string, phone?: string, email?: string}>>
     */
    private function audience(): array
    {
        return [
            MessageDelivery::ASSIGNED => [[
                'name' => $this->assignee->name,
                'email' => (string) $this->assignee->email,
            ]],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function variables(): array
    {
        $ticket = $this->ticket;

        return [
            'responsavel.nome' => $this->assignee->name,
            'responsavel.primeiro_nome' => Str::before(trim($this->assignee->name), ' '),
            'ticket.numero' => (string) $ticket->number,
            'ticket.assunto' => (string) $ticket->subject,
            'ticket.prioridade' => Ticket::PRIORITY_LABELS[$ticket->priority] ?? $ticket->priority,
            'ticket.categoria' => (string) $ticket->category,
            'cliente.nome' => (string) ($ticket->client?->display_name ?? ''),
            'ticket.link' => url('/tickets/'.$ticket->id),
            'agencia.nome' => 'Agência May',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function facts(): array
    {
        return [
            'prioridade' => (string) $this->ticket->priority,
            'tem_cliente' => $this->ticket->client_id !== null,
        ];
    }

    /**
     * O texto que sai sem nenhum modelo cadastrado — e o ponto de partida do editor.
     */
    public static function defaultBody(): string
    {
        return implode("\n", [
            '🎫 Novo ticket com você — {{agencia.nome}}',
            '',
            'Olá[[, {{responsavel.primeiro_nome}}]]!',
            'O ticket {{ticket.numero}} foi atribuído a você.',
            '',
            'Assunto: {{ticket.assunto}}',
            'Prioridade: {{ticket.prioridade}}',
            '[[Cliente: {{cliente.nome}}]]',
            '',
            'Abrir: {{ticket.link}}',
        ]);
    }
}
