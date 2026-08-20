<?php

namespace App\Support;

/**
 * Os pontos do sistema que mandam mensagem, e o que cada um sabe contar.
 *
 * O catálogo fica em código, e não no banco, porque um gatilho só existe de
 * verdade quando alguma tela o dispara: cadastrar "aniversário do cliente" numa
 * tabela não faria nenhuma mensagem sair. Acrescentar um gatilho é escrever uma
 * entrada aqui e chamar o compositor no lugar que manda.
 *
 * Cada entrada declara duas listas diferentes:
 *
 * - `variables` são os marcadores que entram no texto — {{cliente.contato}};
 * - `fields` são os fatos sobre os quais se escreve regra — "itens não
 *   necessários é maior que 0". Nem todo fato vira texto, nem todo texto vira
 *   regra, e misturar os dois numa lista só confundiria quem escreve.
 */
final class MessageTriggers
{
    public const MAINTENANCE_DONE = 'manutencao.concluida';

    public const INVOICE_DUE = 'financeiro.cobranca';

    public const CONTRACT_EXPIRING = 'contrato.vencimento';

    public const CONTRACT_PRICE_REVIEW = 'contrato.reajuste';

    public const CONTRACT_PRICE_REVIEW_NOTICE = 'contrato.reajuste.aviso';

    public const TICKET_ASSIGNED = 'ticket.atribuido';

    /**
     * @var array<string, array{
     *     label: string,
     *     module: string,
     *     description: string,
     *     variables: list<array{key: string, label: string, example: string}>,
     *     fields: list<array{key: string, label: string, type: string}>,
     * }>
     */
    public const CATALOG = [
        self::MAINTENANCE_DONE => [
            'label' => 'Manutenção concluída',
            'module' => 'manutencao',
            'description' => 'Sai para o cliente quando você registra uma manutenção e marca para avisar.',
            'variables' => [
                ['key' => 'cliente.nome', 'label' => 'Nome do cliente', 'example' => 'Padaria Pão Quente Ltda'],
                ['key' => 'cliente.contato', 'label' => 'Pessoa de contato', 'example' => 'Maria Souza'],
                ['key' => 'cliente.primeiro_nome', 'label' => 'Primeiro nome do contato', 'example' => 'Maria'],
                ['key' => 'site.url', 'label' => 'Endereço do site', 'example' => 'paoquente.com.br'],
                ['key' => 'manutencao.data', 'label' => 'Data da manutenção', 'example' => '17/08/2026'],
                ['key' => 'manutencao.mes', 'label' => 'Mês da manutenção', 'example' => 'agosto'],
                ['key' => 'manutencao.itens', 'label' => 'Lista do que foi feito', 'example' => "✅ Backup completo\n✅ Atualização do WordPress\n☑️ Correção de links quebrados (não era necessário)"],
                ['key' => 'manutencao.observacoes', 'label' => 'Suas observações', 'example' => 'O plugin de formulário foi substituído por um mais leve.'],
                ['key' => 'agencia.nome', 'label' => 'Nome da agência', 'example' => 'Agência May'],
            ],
            'fields' => [
                ['key' => 'itens_feitos', 'label' => 'Itens executados', 'type' => 'number'],
                ['key' => 'itens_nao_necessarios', 'label' => 'Itens não necessários', 'type' => 'number'],
                ['key' => 'tem_observacoes', 'label' => 'Tem observações', 'type' => 'boolean'],
                ['key' => 'cliente', 'label' => 'Nome do cliente', 'type' => 'text'],
                ['key' => 'site', 'label' => 'Endereço do site', 'type' => 'text'],
                ['key' => 'mes', 'label' => 'Mês da manutenção', 'type' => 'number'],
            ],
        ],
        self::INVOICE_DUE => [
            'label' => 'Cobrança de fatura',
            'module' => 'financeiro',
            'description' => 'Enviada ao cliente para cobrar uma fatura (conta a receber) em aberto.',
            'variables' => [
                ['key' => 'cliente.nome', 'label' => 'Nome do cliente', 'example' => 'Padaria Pão Quente Ltda'],
                ['key' => 'cliente.contato', 'label' => 'Pessoa de contato', 'example' => 'Maria Souza'],
                ['key' => 'cliente.primeiro_nome', 'label' => 'Primeiro nome do contato', 'example' => 'Maria'],
                ['key' => 'fatura.descricao', 'label' => 'Descrição da fatura', 'example' => 'Mensalidade de hospedagem — agosto'],
                ['key' => 'fatura.valor', 'label' => 'Valor original', 'example' => 'R$ 250,00'],
                ['key' => 'fatura.valor_atualizado', 'label' => 'Valor atualizado (multa + juros)', 'example' => 'R$ 262,92'],
                ['key' => 'fatura.vencimento', 'label' => 'Data de vencimento', 'example' => '10/08/2026'],
                ['key' => 'fatura.dias_atraso', 'label' => 'Dias em atraso', 'example' => '8'],
                ['key' => 'fatura.multa', 'label' => 'Multa', 'example' => 'R$ 12,50'],
                ['key' => 'fatura.juros', 'label' => 'Juros', 'example' => 'R$ 0,42'],
                ['key' => 'fatura.numero', 'label' => 'Número da fatura (Asaas)', 'example' => '00012345'],
                ['key' => 'fatura.link', 'label' => 'Link de pagamento (Asaas)', 'example' => 'https://www.asaas.com/i/00012345'],
                ['key' => 'agencia.nome', 'label' => 'Nome da agência', 'example' => 'Agência May'],
            ],
            'fields' => [
                ['key' => 'dias_atraso', 'label' => 'Dias em atraso', 'type' => 'number'],
                ['key' => 'valor', 'label' => 'Valor', 'type' => 'number'],
                ['key' => 'vencida', 'label' => 'Está vencida', 'type' => 'boolean'],
                ['key' => 'cliente', 'label' => 'Nome do cliente', 'type' => 'text'],
            ],
        ],
        self::CONTRACT_EXPIRING => [
            'label' => 'Contrato a vencer',
            'module' => 'contratos',
            'description' => 'Aviso interno (aos administradores) quando um contrato se aproxima do fim — 30, 15, 7 e 1 dia antes. Configure os destinatários como "Os administradores".',
            'variables' => [
                ['key' => 'cliente.nome', 'label' => 'Nome do cliente', 'example' => 'Padaria Pão Quente Ltda'],
                ['key' => 'contrato.numero', 'label' => 'Número do contrato', 'example' => '0007'],
                ['key' => 'contrato.servico', 'label' => 'Serviço', 'example' => 'Hospedagem anual'],
                ['key' => 'contrato.valor', 'label' => 'Valor', 'example' => 'R$ 1.200,00'],
                ['key' => 'contrato.fim', 'label' => 'Data de fim', 'example' => '31/12/2026'],
                ['key' => 'contrato.dias', 'label' => 'Dias até o fim', 'example' => '30'],
                ['key' => 'agencia.nome', 'label' => 'Nome da agência', 'example' => 'Agência May'],
            ],
            'fields' => [
                ['key' => 'dias', 'label' => 'Dias até o fim', 'type' => 'number'],
                ['key' => 'valor', 'label' => 'Valor', 'type' => 'number'],
                ['key' => 'cliente', 'label' => 'Nome do cliente', 'type' => 'text'],
                ['key' => 'servico', 'label' => 'Serviço', 'type' => 'text'],
            ],
        ],
        self::CONTRACT_PRICE_REVIEW => [
            'label' => 'Reajuste de preço',
            'module' => 'contratos',
            'description' => 'Aviso interno (aos administradores) quando chega a hora de reajustar o preço de um contrato — 30, 15, 7 e 1 dia antes. Configure os destinatários como "Os administradores".',
            'variables' => [
                ['key' => 'cliente.nome', 'label' => 'Nome do cliente', 'example' => 'Padaria Pão Quente Ltda'],
                ['key' => 'contrato.numero', 'label' => 'Número do contrato', 'example' => '0007'],
                ['key' => 'contrato.servico', 'label' => 'Serviço', 'example' => 'Hospedagem anual'],
                ['key' => 'contrato.valor', 'label' => 'Valor atual', 'example' => 'R$ 1.200,00'],
                ['key' => 'contrato.reajuste', 'label' => 'Data do reajuste', 'example' => '01/01/2028'],
                ['key' => 'contrato.dias', 'label' => 'Dias até o reajuste', 'example' => '30'],
                ['key' => 'agencia.nome', 'label' => 'Nome da agência', 'example' => 'Agência May'],
            ],
            'fields' => [
                ['key' => 'dias', 'label' => 'Dias até o reajuste', 'type' => 'number'],
                ['key' => 'valor', 'label' => 'Valor atual', 'type' => 'number'],
                ['key' => 'cliente', 'label' => 'Nome do cliente', 'type' => 'text'],
                ['key' => 'servico', 'label' => 'Serviço', 'type' => 'text'],
            ],
        ],
        self::CONTRACT_PRICE_REVIEW_NOTICE => [
            'label' => 'Aviso de reajuste ao cliente',
            'module' => 'contratos',
            'description' => 'Enviado ao cliente para avisar do reajuste antes de aplicá-lo — pelo botão em Contratos ou no Dashboard. Sai por e-mail, em HTML. Configure os destinatários como "O cliente".',
            'variables' => [
                ['key' => 'cliente.nome', 'label' => 'Nome do cliente', 'example' => 'Padaria Pão Quente Ltda'],
                ['key' => 'cliente.contato', 'label' => 'Pessoa de contato', 'example' => 'Maria Souza'],
                ['key' => 'cliente.primeiro_nome', 'label' => 'Primeiro nome do contato', 'example' => 'Maria'],
                ['key' => 'contrato.numero', 'label' => 'Número do contrato', 'example' => '0007'],
                ['key' => 'contrato.servico', 'label' => 'Serviço', 'example' => 'Hospedagem anual'],
                ['key' => 'contrato.valor', 'label' => 'Valor atual', 'example' => 'R$ 1.200,00'],
                ['key' => 'contrato.valor_novo', 'label' => 'Novo valor', 'example' => 'R$ 1.320,00'],
                ['key' => 'contrato.aumento', 'label' => 'Aumento (%)', 'example' => '10%'],
                ['key' => 'contrato.reajuste', 'label' => 'Data do reajuste', 'example' => '01/01/2028'],
                ['key' => 'agencia.nome', 'label' => 'Nome da agência', 'example' => 'Agência May'],
            ],
            'fields' => [
                ['key' => 'valor', 'label' => 'Valor atual', 'type' => 'number'],
                ['key' => 'valor_novo', 'label' => 'Novo valor', 'type' => 'number'],
                ['key' => 'cliente', 'label' => 'Nome do cliente', 'type' => 'text'],
                ['key' => 'servico', 'label' => 'Serviço', 'type' => 'text'],
            ],
        ],
        self::TICKET_ASSIGNED => [
            'label' => 'Ticket atribuído',
            'module' => 'tickets',
            'description' => 'Avisa o responsável quando um ticket passa a ser dele. Configure os destinatários como "Quem executou".',
            'variables' => [
                ['key' => 'responsavel.nome', 'label' => 'Nome do responsável', 'example' => 'João Pereira'],
                ['key' => 'responsavel.primeiro_nome', 'label' => 'Primeiro nome do responsável', 'example' => 'João'],
                ['key' => 'ticket.numero', 'label' => 'Número do ticket', 'example' => 'T0007'],
                ['key' => 'ticket.assunto', 'label' => 'Assunto', 'example' => 'Site fora do ar'],
                ['key' => 'ticket.prioridade', 'label' => 'Prioridade', 'example' => 'Urgente'],
                ['key' => 'ticket.categoria', 'label' => 'Categoria', 'example' => 'Suporte'],
                ['key' => 'cliente.nome', 'label' => 'Cliente', 'example' => 'Padaria Pão Quente Ltda'],
                ['key' => 'ticket.link', 'label' => 'Link do ticket', 'example' => 'https://portal.agenciamay.com.br/tickets/7'],
                ['key' => 'agencia.nome', 'label' => 'Nome da agência', 'example' => 'Agência May'],
            ],
            'fields' => [
                ['key' => 'prioridade', 'label' => 'Prioridade', 'type' => 'text'],
                ['key' => 'tem_cliente', 'label' => 'Tem cliente vinculado', 'type' => 'boolean'],
            ],
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function exists(string $trigger): bool
    {
        return isset(self::CATALOG[$trigger]);
    }

    public static function labelFor(string $trigger): string
    {
        return self::CATALOG[$trigger]['label'] ?? $trigger;
    }

    /**
     * Os marcadores que este gatilho sabe responder.
     *
     * @return list<string>
     */
    public static function variableKeys(string $trigger): array
    {
        return array_column(self::CATALOG[$trigger]['variables'] ?? [], 'key');
    }

    /** @return list<string> */
    public static function fieldKeys(string $trigger): array
    {
        return array_column(self::CATALOG[$trigger]['fields'] ?? [], 'key');
    }

    /**
     * Um exemplo de cada variável, para a pré-visualização do editor.
     *
     * @return array<string, string>
     */
    public static function examples(string $trigger): array
    {
        return array_column(self::CATALOG[$trigger]['variables'] ?? [], 'example', 'key');
    }

    /**
     * Marcadores escritos no texto que este gatilho não sabe responder.
     *
     * Sai daqui para a validação: um {{cliente.aniversario}} que ninguém
     * preenche chegaria em branco no WhatsApp do cliente, e quem escreveu
     * jamais saberia por quê.
     *
     * @return list<string>
     */
    public static function unknownIn(string $trigger, string $text): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', $text, $matches);

        $conhecidos = self::variableKeys($trigger);

        return array_values(array_unique(array_filter(
            $matches[1] ?? [],
            fn (string $key) => ! in_array($key, $conhecidos, true)
        )));
    }
}

