<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\User;

/**
 * O aviso de que um contrato está perto de vencer.
 *
 * Sai para a agência, nunca para o cliente: renovar é uma decisão interna, e
 * mandar um "seu contrato acaba" direto ao cliente seria outra coisa. Por isso
 * a audiência só tem os administradores — mesmo um modelo mal configurado não
 * alcança o cliente, porque ele não está aqui.
 *
 * Separado do envio como os outros avisos: o texto se lê e se testa; entregar é
 * transporte. Por onde sai e para quem, dentro dos administradores, é o modelo
 * cadastrado em Configurações › Mensagens que decide.
 */
final class ContractExpiryAlert
{
    public function __construct(private readonly Contract $contract) {}

    /**
     * @return array{ok: bool, message: string, sent: list<string>, errors: list<string>}
     */
    public function send(): array
    {
        return Notifier::dispatch(
            MessageTriggers::CONTRACT_EXPIRING,
            $this->variables(),
            $this->facts(),
            $this->audience(),
            MessageComposer::render(self::defaultBody(), $this->variables()),
        );
    }

    /**
     * Só a agência. O cliente nunca entra nesta lista, de propósito.
     *
     * @return array<string, list<array{name?: string, email?: string, phone?: string}>>
     */
    private function audience(): array
    {
        return [
            MessageDelivery::ADMINS => User::query()
                ->where('role', 'admin')
                ->get()
                ->map(fn (User $user) => ['name' => $user->name, 'email' => $user->email])
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        $client = $this->contract->client;
        $value = $this->contract->value;

        return [
            'cliente.nome' => (string) $client->name,
            'contrato.numero' => (string) $this->contract->number,
            'contrato.servico' => (string) $this->contract->service,
            'contrato.valor' => $value === null ? '' : 'R$ '.number_format((float) $value, 2, ',', '.'),
            'contrato.fim' => $this->contract->ends_at?->format('d/m/Y') ?? '',
            'contrato.dias' => (string) max(0, (int) $this->contract->daysLeft()),
            'agencia.nome' => 'Agência May',
        ];
    }

    /**
     * O que as regras do modelo podem perguntar.
     *
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        return [
            'dias' => max(0, (int) $this->contract->daysLeft()),
            'valor' => (float) ($this->contract->value ?? 0),
            'cliente' => (string) $this->contract->client->name,
            'servico' => (string) $this->contract->service,
        ];
    }

    /**
     * O texto padrão, para o aviso não depender de alguém ter cadastrado um modelo.
     */
    public static function defaultBody(): string
    {
        return implode("\n", [
            '⏰ *Contrato a vencer* — {{agencia.nome}}',
            '',
            'Contrato {{contrato.numero}} — {{cliente.nome}}',
            'Serviço: {{contrato.servico}}[[ — {{contrato.valor}}]]',
            'Vence em {{contrato.fim}} (faltam {{contrato.dias}} dias).',
            '',
            'Hora de avaliar a renovação.',
        ]);
    }
}
