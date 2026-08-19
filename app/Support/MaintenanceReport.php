<?php

namespace App\Support;

use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * O relatório que sai quando a manutenção é concluída.
 *
 * Separado do envio de propósito: o texto é a parte que se lê, se revisa e se
 * testa; mandar é detalhe de transporte. Por onde ele sai — WhatsApp, e-mail,
 * os dois — e para quem é decisão do modelo cadastrado.
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
     * @return array{ok: bool, message: string, sent: list<string>, errors: list<string>}
     */
    public function send(): array
    {
        $resultado = Notifier::dispatch(
            MessageTriggers::MAINTENANCE_DONE,
            $this->variables(),
            $this->facts(),
            $this->audience(),
            MessageComposer::render(self::defaultBody(), $this->variables()),
        );

        $this->maintenance->forceFill([
            'notified_at' => $resultado['ok'] ? now() : null,
            'notify_error' => $resultado['ok'] ? null : $resultado['message'],
        ])->saveQuietly();

        return $resultado;
    }

    /**
     * Quem pode ser avisado desta manutenção.
     *
     * @return array<string, list<array{name?: string, phone?: string, email?: string}>>
     */
    private function audience(): array
    {
        $client = $this->maintenance->plan->client;
        $executou = $this->maintenance->user;

        return [
            MessageDelivery::CLIENT => [[
                'name' => (string) $client->contact_name,
                'phone' => (string) $client->phone,
                'email' => (string) $client->email,
            ]],
            MessageDelivery::ADMINS => User::query()
                ->where('role', 'admin')
                ->get()
                ->map(fn (User $user) => ['name' => $user->name, 'email' => $user->email])
                ->all(),
            MessageDelivery::ASSIGNED => $executou
                ? [['name' => $executou->name, 'email' => $executou->email]]
                : [],
        ];
    }

    /**
     * O texto do relatório.
     *
     * Quem manda no texto é o modelo cadastrado em Configurações › Mensagens.
     * Sem nenhum que sirva, sai o padrão embutido abaixo — o relatório não
     * pode depender de alguém ter lembrado de cadastrar um modelo.
     */
    public function text(): string
    {
        return MessageComposer::compose(
            MessageTriggers::MAINTENANCE_DONE,
            $this->variables(),
            $this->facts(),
            $this->defaultText(),
        )['text'];
    }

    /**
     * O que os marcadores do texto valem nesta manutenção.
     *
     * @return array<string, string>
     */
    public function variables(): array
    {
        $plan = $this->maintenance->plan;
        $client = $plan->client;
        $performed = $this->maintenance->performed_at;

        return [
            'cliente.nome' => (string) $client->name,
            'cliente.contato' => (string) $client->contact_name,
            'cliente.primeiro_nome' => Str::before(trim((string) $client->contact_name), ' '),
            'site.url' => (string) $plan->site_url,
            'manutencao.data' => $performed->format('d/m/Y'),
            'manutencao.mes' => $performed->translatedFormat('F'),
            'manutencao.itens' => implode("\n", $this->reportedItems()),
            'manutencao.observacoes' => (string) $this->maintenance->notes,
            'agencia.nome' => 'Agência May',
        ];
    }

    /**
     * O que as regras podem perguntar sobre esta manutenção.
     *
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        $items = $this->maintenance->items ?? [];
        $contar = fn (string $result) => count(array_filter($items, fn ($item) => ($item['result'] ?? null) === $result));

        return [
            'itens_feitos' => $contar(MaintenanceChecklist::DONE),
            'itens_nao_necessarios' => $contar(MaintenanceChecklist::NOT_NEEDED),
            'tem_observacoes' => filled($this->maintenance->notes),
            'cliente' => (string) $this->maintenance->plan->client->name,
            'site' => (string) $this->maintenance->plan->site_url,
            'mes' => (int) $this->maintenance->performed_at->format('n'),
        ];
    }

    /**
     * As linhas do checklist que o cliente vê.
     *
     * "Pular" não aparece: para o cliente, um item pulado seria ruído — ele não
     * foi avaliado, então não há o que relatar. Fica na tela interna, que é onde
     * serve para alguém retomar.
     *
     * @return list<string>
     */
    private function reportedItems(): array
    {
        $lines = [];

        foreach ($this->maintenance->items ?? [] as $item) {
            $line = match ($item['result'] ?? null) {
                MaintenanceChecklist::DONE => "✅ {$item['label']}",
                MaintenanceChecklist::NOT_NEEDED => "☑️ {$item['label']} (não era necessário)",
                default => null,
            };

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * O relatório que sai sem nenhum modelo cadastrado.
     *
     * É também o ponto de partida oferecido no editor: começar de um texto que
     * já funciona é mais rápido do que encarar uma caixa em branco.
     */
    public static function defaultBody(): string
    {
        return implode("\n", [
            '*Relatório de Manutenção* — {{agencia.nome}}',
            '',
            'Olá[[, {{cliente.contato}}]]!',
            'Concluímos a manutenção preventiva do site *{{site.url}}* em {{manutencao.data}}.',
            '',
            '{{manutencao.itens}}',
            '',
            '[[_{{manutencao.observacoes}}_]]',
            '',
            'Qualquer dúvida, é só chamar por aqui.',
        ]);
    }

    private function defaultText(): string
    {
        return MessageComposer::render(self::defaultBody(), $this->variables());
    }
}

