<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\User;

/**
 * O aviso de que chegou a hora de reajustar o preço de um contrato.
 *
 * Como o de vencimento, sai só para a agência — reajustar é decisão interna. O
 * cliente nunca entra na audiência, de propósito.
 */
final class ContractPriceReviewAlert
{
    public function __construct(private readonly Contract $contract) {}

    /**
     * @return array{ok: bool, message: string, sent: list<string>, errors: list<string>}
     */
    public function send(): array
    {
        return Notifier::dispatch(
            MessageTriggers::CONTRACT_PRICE_REVIEW,
            $this->variables(),
            $this->facts(),
            $this->audience(),
            MessageComposer::render(self::defaultBody(), $this->variables()),
        );
    }

    /**
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
        $value = $this->contract->value;

        return [
            'cliente.nome' => (string) $this->contract->client->name,
            'contrato.numero' => (string) $this->contract->number,
            'contrato.servico' => (string) $this->contract->service,
            'contrato.valor' => $value === null ? '' : 'R$ '.number_format((float) $value, 2, ',', '.'),
            'contrato.reajuste' => $this->contract->price_review_at?->format('d/m/Y') ?? '',
            'contrato.dias' => (string) max(0, (int) $this->contract->daysToReview()),
            'agencia.nome' => 'Agência May',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        return [
            'dias' => max(0, (int) $this->contract->daysToReview()),
            'valor' => (float) ($this->contract->value ?? 0),
            'cliente' => (string) $this->contract->client->name,
            'servico' => (string) $this->contract->service,
        ];
    }

    public static function defaultBody(): string
    {
        return implode("\n", [
            '📈 *Reajuste de preço* — {{agencia.nome}}',
            '',
            'Contrato {{contrato.numero}} — {{cliente.nome}}',
            'Serviço: {{contrato.servico}}[[ — valor atual {{contrato.valor}}]]',
            'Reajuste previsto para {{contrato.reajuste}} (faltam {{contrato.dias}} dias).',
            '',
            'Hora de revisar o valor.',
        ]);
    }
}
