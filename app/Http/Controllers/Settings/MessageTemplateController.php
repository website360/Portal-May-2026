<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Support\MaintenanceReport;
use App\Support\MessageComposer;
use App\Support\MessageDelivery;
use App\Support\MessageRules;
use App\Support\MessageTriggers;
use App\Support\Smtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Os modelos de mensagem do WhatsApp.
 *
 * Ficam nas configurações pela mesma razão dos modelos de contrato: são a
 * matéria-prima do que o sistema envia, e não o trabalho do dia a dia.
 */
class MessageTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('configuracoes/mensagens', [
            'templates' => MessageTemplate::query()
                ->orderBy('trigger')
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get()
                ->map(fn (MessageTemplate $template) => [
                    'id' => $template->id,
                    'trigger' => $template->trigger,
                    'name' => $template->name,
                    'description' => $template->description,
                    'variations' => $template->variations ?? [],
                    'channels' => $template->channels ?: [MessageDelivery::WHATSAPP],
                    'recipients' => $template->recipients ?: [MessageDelivery::CLIENT],
                    'subject' => $template->subject,
                    'conditions' => $template->conditions ?? [],
                    'priority' => $template->priority,
                    'active' => $template->active,
                    'rules' => MessageRules::describe(
                        $template->conditions ?? [],
                        self::fieldLabels($template->trigger)
                    ),
                ])
                ->all(),
            'triggers' => MessageTriggers::CATALOG,
            'operators' => MessageRules::OPERATORS,
            'operators_without_value' => MessageRules::WITHOUT_VALUE,
            'channels' => MessageDelivery::CHANNELS,
            'recipient_kinds' => MessageDelivery::RECIPIENTS,
            // Marcar "e-mail" num sistema sem servidor cadastrado não manda nada.
            'mail_ready' => Smtp::configured(),
            // O texto que sai hoje sem nenhum modelo: ponto de partida do editor.
            'starters' => [MessageTriggers::MAINTENANCE_DONE => MaintenanceReport::defaultBody()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = MessageTemplate::create($this->validated($request));

        return back()->with('success', "Modelo {$template->name} criado.");
    }

    public function update(Request $request, MessageTemplate $modelo): RedirectResponse
    {
        $modelo->update($this->validated($request));

        return back()->with('success', "Modelo {$modelo->name} salvo.");
    }

    public function destroy(MessageTemplate $modelo): RedirectResponse
    {
        $nome = $modelo->name;
        $modelo->delete();

        return back()->with('success', "Modelo {$nome} removido.");
    }

    /**
     * Como o texto fica com dados de exemplo.
     *
     * A conta é a mesma do envio de verdade — inclusive os blocos opcionais —
     * porque uma pré-visualização que renderiza diferente do envio é pior do
     * que não ter pré-visualização.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trigger' => ['required', Rule::in(MessageTriggers::keys())],
            'body' => ['nullable', 'string', 'max:4000'],
        ]);

        return response()->json([
            'text' => MessageComposer::render((string) ($data['body'] ?? ''), MessageTriggers::examples($data['trigger'])),
            'unknown' => MessageTriggers::unknownIn($data['trigger'], (string) ($data['body'] ?? '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $trigger = (string) $request->input('trigger');

        $data = $request->validate([
            'trigger' => ['required', Rule::in(MessageTriggers::keys())],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'variations' => ['required', 'array', 'min:1', 'max:10'],
            'variations.*' => ['required', 'string', 'max:4000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(MessageDelivery::channelKeys())],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => [Rule::in(MessageDelivery::recipientKeys())],
            // O assunto só existe para e-mail — é o único canal que tem um.
            'subject' => [Rule::requiredIf(in_array(MessageDelivery::EMAIL, (array) $request->input('channels', []), true)), 'nullable', 'string', 'max:200'],
            'conditions' => ['nullable', 'array', 'max:10'],
            'conditions.*.field' => ['required', Rule::in(MessageTriggers::fieldKeys($trigger))],
            'conditions.*.operator' => ['required', Rule::in(array_keys(MessageRules::OPERATORS))],
            'conditions.*.value' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['boolean'],
        ], [], [
            'trigger' => 'gatilho',
            'name' => 'nome',
            'variations' => 'variações',
            'variations.*' => 'variação',
            'channels' => 'canais',
            'recipients' => 'destinatários',
            'subject' => 'assunto',
            'priority' => 'prioridade',
        ]);

        // O assunto do e-mail também aceita marcadores, e vale a mesma regra.
        $desconhecidosNoAssunto = MessageTriggers::unknownIn($trigger, (string) ($data['subject'] ?? ''));

        if ($desconhecidosNoAssunto !== []) {
            throw ValidationException::withMessages([
                'subject' => 'Não conheço {{'.implode('}}, {{', $desconhecidosNoAssunto).'}} neste gatilho.',
            ]);
        }

        /*
         * Marcador que o gatilho não conhece chegaria em branco no WhatsApp do
         * cliente, e quem escreveu nunca saberia por quê. Barrar aqui é a única
         * hora em que alguém está olhando.
         */
        foreach ($data['variations'] as $indice => $variacao) {
            $desconhecidos = MessageTriggers::unknownIn($trigger, $variacao);

            if ($desconhecidos !== []) {
                throw ValidationException::withMessages([
                    "variations.{$indice}" => 'Não conheço {{'.implode('}}, {{', $desconhecidos).'}} neste gatilho.',
                ]);
            }
        }

        $data['conditions'] = array_values($data['conditions'] ?? []);
        $data['priority'] = $data['priority'] ?? 0;

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private static function fieldLabels(string $trigger): array
    {
        return array_column(MessageTriggers::CATALOG[$trigger]['fields'] ?? [], 'label', 'key');
    }
}
