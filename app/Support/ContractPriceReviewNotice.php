<?php

namespace App\Support;

use App\Models\Contract;
use Illuminate\Support\Str;

/**
 * O aviso de reajuste que vai para o cliente, em HTML.
 *
 * O texto é o do modelo em Configurações › Mensagens (gatilho
 * contrato.reajuste.aviso); este só o embrulha no layout e destaca os valores.
 * Como os outros avisos, nunca lança: o motivo volta na resposta.
 */
final class ContractPriceReviewNotice
{
    public function __construct(
        private readonly Contract $contract,
        private readonly float $newValue,
    ) {}

    /**
     * Compõe, renderiza e envia. Devolve o que houve, para a tela contar.
     *
     * @return array{ok: bool, message: string}
     */
    public function send(): array
    {
        $client = $this->contract->client;

        if ($client === null || blank($client->email)) {
            return ['ok' => false, 'message' => 'O cliente não tem e-mail cadastrado.'];
        }

        $variables = $this->variables();

        $composed = MessageComposer::compose(
            MessageTriggers::CONTRACT_PRICE_REVIEW_NOTICE,
            $variables,
            $this->facts(),
            MessageComposer::render(self::defaultBody(), $variables),
        );

        $template = $composed['template'];
        $subject = filled($template?->subject)
            ? MessageComposer::render($template->subject, $variables)
            : 'Reajuste do contrato '.$this->contract->number;

        $html = view('emails.reajuste', [
            'subject' => $subject,
            'message' => $composed['text'],
            'agency' => 'Agência May',
            'number' => (string) $this->contract->number,
            'service' => (string) $this->contract->service,
            'valueOld' => self::brl($this->currentValue()),
            'valueNew' => self::brl($this->newValue),
            'increase' => $this->increaseLabel(),
            'reviewDate' => $this->contract->price_review_at?->format('d/m/Y') ?? '—',
        ])->render();

        return Smtp::sendHtml($client->email, $subject, $html);
    }

    private function currentValue(): float
    {
        return (float) ($this->contract->value ?? 0);
    }

    /** O aumento em percentual, escrito curto: "10%", "8,3%". */
    private function increaseLabel(): string
    {
        $old = $this->currentValue();

        if ($old <= 0) {
            return '—';
        }

        $pct = (($this->newValue - $old) / $old) * 100;

        return rtrim(rtrim(number_format($pct, 1, ',', '.'), '0'), ',').'%';
    }

    /**
     * @return array<string, string>
     */
    private function variables(): array
    {
        $contract = $this->contract;
        $client = $contract->client;
        $contact = (string) ($client?->contact_name ?? '');

        return [
            'cliente.nome' => (string) ($client?->name ?? ''),
            'cliente.contato' => $contact,
            'cliente.primeiro_nome' => Str::before(trim($contact), ' '),
            'contrato.numero' => (string) $contract->number,
            'contrato.servico' => (string) $contract->service,
            'contrato.valor' => self::brl($this->currentValue()),
            'contrato.valor_novo' => self::brl($this->newValue),
            'contrato.aumento' => $this->increaseLabel(),
            'contrato.reajuste' => $contract->price_review_at?->format('d/m/Y') ?? '',
            'agencia.nome' => 'Agência May',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function facts(): array
    {
        return [
            'valor' => $this->currentValue(),
            'valor_novo' => $this->newValue,
            'cliente' => (string) ($this->contract->client?->name ?? ''),
            'servico' => (string) $this->contract->service,
        ];
    }

    private static function brl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    /**
     * O texto que sai sem nenhum modelo cadastrado — e o ponto de partida do editor.
     */
    public static function defaultBody(): string
    {
        return implode("\n", [
            'Olá[[, {{cliente.primeiro_nome}}]]!',
            '',
            'Passamos para avisar que o seu contrato {{contrato.numero}} ({{contrato.servico}}) será reajustado a partir de {{contrato.reajuste}}.',
            '',
            'O valor passa de {{contrato.valor}} para {{contrato.valor_novo}} — um aumento de {{contrato.aumento}}, que acompanha os custos do serviço.',
            '',
            'Seguimos à disposição para qualquer dúvida.',
        ]);
    }
}
