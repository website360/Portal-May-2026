<?php

namespace App\Support;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A cobrança que sai para o cliente de uma fatura (transação a receber) em aberto.
 *
 * Espelho do relatório de manutenção: o texto vem de um modelo cadastrado em
 * Configurações › Mensagens; por onde sai e para quem, também. O valor já vai
 * atualizado com a multa e os juros do dia.
 */
final class ChargeReminder
{
    public function __construct(private readonly Transaction $transaction) {}

    /**
     * O que seria enviado, e para quem — sem enviar. Para a tela conferir antes.
     *
     * @return array{text: string, recipients: list<array<string, mixed>>}
     */
    public function preview(): array
    {
        return Notifier::preview(
            MessageTriggers::INVOICE_DUE,
            $this->variables(),
            $this->facts(),
            $this->audience(),
            MessageComposer::render(self::defaultBody(), $this->variables()),
        );
    }

    /**
     * @return array{ok: bool, message: string, sent: list<string>, errors: list<string>}
     */
    public function send(): array
    {
        $result = Notifier::dispatch(
            MessageTriggers::INVOICE_DUE,
            $this->variables(),
            $this->facts(),
            $this->audience(),
            MessageComposer::render(self::defaultBody(), $this->variables()),
        );

        Charges::record($this->transaction->id, $result['ok'], $result['message'], [
            'client' => (string) $this->transaction->client?->display_name,
            'description' => (string) $this->transaction->description,
            'amount' => self::brl((float) $this->fees()['total']),
            'channels' => implode(', ', $result['sent']),
        ]);

        return $result;
    }

    /**
     * @return array{days_late: int, late: bool, amount: float, fine: float, interest: float, total: float, discount: float, daily_rate: float}
     */
    private function fees(): array
    {
        return LateFee::calculate((float) $this->transaction->amount, $this->transaction->due_date, Carbon::today());
    }

    private static function brl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        $client = $this->transaction->client;
        $fees = $this->fees();
        $asaas = \App\Support\AsaasLinks::forTransaction($this->transaction->id);

        return [
            'cliente.nome' => (string) $client?->name,
            'cliente.contato' => (string) $client?->contact_name,
            'cliente.primeiro_nome' => Str::before(trim((string) $client?->contact_name), ' '),
            'fatura.descricao' => (string) $this->transaction->description,
            'fatura.valor' => self::brl($fees['amount']),
            'fatura.valor_atualizado' => self::brl($fees['total']),
            'fatura.vencimento' => $this->transaction->due_date->format('d/m/Y'),
            'fatura.dias_atraso' => (string) $fees['days_late'],
            'fatura.multa' => self::brl($fees['fine']),
            'fatura.juros' => self::brl($fees['interest']),
            'fatura.numero' => (string) ($asaas['invoice_number'] ?? ''),
            'fatura.link' => (string) ($asaas['invoice_url'] ?? ''),
            'agencia.nome' => 'Agência May',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        $fees = $this->fees();

        return [
            'dias_atraso' => $fees['days_late'],
            'valor' => (float) $this->transaction->amount,
            'vencida' => $fees['late'],
            'cliente' => (string) $this->transaction->client?->name,
        ];
    }

    /**
     * @return array<string, list<array{name?: string, phone?: string, email?: string}>>
     */
    private function audience(): array
    {
        $client = $this->transaction->client;

        return [
            MessageDelivery::CLIENT => $client ? [[
                'name' => (string) $client->contact_name,
                'phone' => (string) $client->phone,
                'email' => (string) $client->email,
            ]] : [],
            MessageDelivery::ADMINS => User::query()
                ->where('role', 'admin')
                ->get()
                ->map(fn (User $user) => ['name' => $user->name, 'email' => $user->email])
                ->all(),
            MessageDelivery::ASSIGNED => [],
        ];
    }

    public static function defaultBody(): string
    {
        return implode("\n", [
            'Olá[[, {{cliente.contato}}]]!',
            'Passando para lembrar da fatura *{{fatura.descricao}}*, no valor de *{{fatura.valor_atualizado}}*, com vencimento em {{fatura.vencimento}}.',
            '',
            '[[Pague por aqui: {{fatura.link}}]]',
            'Qualquer dúvida sobre o pagamento, é só chamar por aqui.',
            '{{agencia.nome}}',
        ]);
    }
}
