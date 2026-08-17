<?php

namespace App\Support;

use App\Models\Maintenance;
use App\Models\WhatsappConnection;

/**
 * O relatório que o cliente recebe no WhatsApp quando a manutenção é concluída.
 *
 * Separado do envio de propósito: o texto é a parte que se lê, se revisa e se
 * testa; mandar é detalhe de transporte.
 */
final class MaintenanceReport
{
    public function __construct(private readonly Maintenance $maintenance) {}

    /**
     * Envia, e devolve o que houve.
     *
     * Nunca lança: uma manutenção registrada continua registrada mesmo que o
     * WhatsApp esteja fora do ar. O motivo fica gravado na própria manutenção,
     * para quem for reenviar depois saber o que faltou.
     *
     * @return array{ok: bool, message: string}
     */
    public function send(): array
    {
        $connection = WhatsappConnection::current();

        if ($connection === null || ! $connection->isConnected()) {
            return $this->record(false, 'WhatsApp não está conectado. Conecte em Configurações › WhatsApp e reenvie.');
        }

        $phone = $this->maintenance->plan->client->phone;

        if (blank($phone)) {
            return $this->record(false, 'O cliente não tem telefone cadastrado.');
        }

        $result = (new Evolution($connection))->sendText($phone, $this->text());

        return $this->record($result['ok'], $result['message']);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function record(bool $ok, string $message): array
    {
        $this->maintenance->forceFill([
            'whatsapp_sent_at' => $ok ? now() : null,
            'whatsapp_error' => $ok ? null : $message,
        ])->saveQuietly();

        return ['ok' => $ok, 'message' => $ok ? 'Relatório enviado no WhatsApp.' : $message];
    }

    /**
     * O texto do relatório.
     *
     * "Pular" não aparece: para o cliente, um item pulado seria ruído — ele não
     * foi avaliado, então não há o que relatar. Fica na tela interna, que é onde
     * serve para alguém retomar.
     */
    public function text(): string
    {
        $plan = $this->maintenance->plan;
        $client = $plan->client;

        $greeting = filled($client->contact_name) ? "Olá, {$client->contact_name}!" : 'Olá!';

        $lines = [
            '*Relatório de Manutenção* — Agência May',
            '',
            $greeting,
            "Concluímos a manutenção preventiva do site *{$plan->site_url}* em ".$this->maintenance->performed_at->format('d/m/Y').'.',
            '',
        ];

        foreach ($this->maintenance->items ?? [] as $item) {
            $lines[] = match ($item['result'] ?? null) {
                MaintenanceChecklist::DONE => "✅ {$item['label']}",
                MaintenanceChecklist::NOT_NEEDED => "➖ {$item['label']} (não era necessário)",
                default => null,
            };
        }

        $lines = array_values(array_filter($lines, fn ($line) => $line !== null));

        if (filled($this->maintenance->notes)) {
            $lines[] = '';
            $lines[] = "_{$this->maintenance->notes}_";
        }

        $lines[] = '';
        $lines[] = 'Qualquer dúvida, é só chamar por aqui.';

        return implode("\n", $lines);
    }
}
