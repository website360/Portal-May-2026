<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

/**
 * O contrato de Loja Virtual da agência, transformado em modelo.
 *
 * Saiu do contrato assinado em outubro de 2025: o texto é o mesmo, com
 * marcadores no lugar do que muda de cliente para cliente. Serve de ponto de
 * partida e de exemplo de como um modelo se escreve.
 */
class ContractTemplateSeeder extends Seeder
{
    public function run(): void
    {
        ContractTemplate::updateOrCreate(
            ['name' => 'Loja Virtual'],
            [
                'description' => 'Desenvolvimento de loja virtual + hospedagem e manutenção',
                'active' => true,
                // Vai para a Clicksign: quem assina é a plataforma.
                'with_signatures' => false,
                'body' => $this->body(),
            ]
        );
    }

    private function body(): string
    {
        return <<<'CONTRATO'
**CONTRATANTE:** {{cliente.qualificacao}}

**CONTRATADA:** {{agencia.qualificacao}}

As partes acima identificadas têm, entre si, justo e acertado o presente Contrato de Prestação de Serviços que se regerá pelas cláusulas seguintes e pelas condições de preço, forma e termo de pagamento descritas no presente.

# Objetivo do contrato

**Cláusula 1ª.** É objetivo do presente contrato a prestação dos seguintes serviços por parte da CONTRATADA ao CONTRATANTE:

- Desenvolvimento de uma Loja Virtual pela CONTRATADA dentro dos padrões e normas atualmente vigentes e utilizando as mais renomadas tecnologias, no caso em questão o Framework Wordpress / Woocommerce.
- Servidor de Hospedagem e Manutenção (Anual)

# A) Da criação da loja virtual

**Cláusula 2ª.** A Loja Virtual a ser desenvolvida terá um layout exclusivo, utilizando a identidade visual (logomarca e cores) fornecida pelo CONTRATANTE à CONTRATADA, assim como todos os textos que irão compor.

**§1º.** Todo conteúdo da loja será gerenciado pelo CONTRATANTE através de um painel de controle totalmente dinâmico, no qual o usuário poderá cadastrar, editar e excluir informações, páginas, imagens e as principais configurações pertinentes à loja.

**§2º.** A criação da loja irá obedecer ao seguinte cronograma de tarefas e prazos (dias úteis), após a assinatura deste contrato:

| Módulo | Ação | Prazo |
| --- | --- | --- |
| 01 | Criação do Layout | 10 dias |
| 02 | Aprovação do Layout | 2 dias |
| 03 | Desenvolvimento | 7 dias |
| 04 | Configurações e Ativação do Servidor | 1 dia |
| 05 | Cadastros de Produtos | 3 dias |
| 06 | Testes, publicação e entrega | 2 dias |
| **Prazo total** | | **25 dias** |

**§3º.** A Loja Virtual a ser desenvolvida possuirá as seguintes características:

- Layout personalizado e responsivo
- Múltiplas páginas
- Otimização de velocidade
- Formulário de contato
- Página de obrigado
- Cadastro de até {{quantidade_produtos}} produtos
- Integração WhatsApp
- Aviso de cookies
- Integração com rede social
- Botões de chamada ao WhatsApp
- Painel de controle
- Além das integrações padrões de pagamentos e fretes

A CONTRATADA firma nesse contrato o compromisso de desenvolvimento da Loja Virtual e manutenção, portanto, é de sua responsabilidade realizar atualizações ou procedimentos de manutenção, bem como é responsável por quaisquer danos provenientes da não atualização/manutenção da Loja Virtual, gerando assim o reparo sem custo nenhum ao CONTRATANTE.

**Parágrafo único.** Uma vez autorizado o layout pelo CONTRATANTE, não serão permitidas novas mudanças, a menos que sejam de fácil execução, segundo definição da CONTRATADA, ou que o CONTRATANTE pague separadamente por essa alteração, de acordo com o acertado entre as partes.

**Cláusula 3ª.** O CONTRATANTE nomeará um único responsável para gerenciar a execução deste contrato, bem como proceder a todas as autorizações, aprovações e solicitações necessárias. Caso ocorra a saída ou destituição, por qualquer motivo, do responsável nomeado anteriormente, o CONTRATANTE deverá nomear novo responsável, o qual não poderá interferir, alterar ou modificar atos praticados pelo responsável anterior.

**Cláusula 4ª.** São obrigações da CONTRATADA o desenvolvimento e implantação dos serviços contratados no prazo e condições definidos na cláusula 1ª, sob pena de suspensão do pagamento, exceto nos casos de atraso por parte do CONTRATANTE em relação à entrega de conteúdos e informações necessárias para o desenvolvimento da Loja Virtual.

**Cláusula 5ª.** Para que o prazo seja cumprido, o CONTRATANTE terá um prazo máximo de 2 (dois) dias para aprovar as etapas do projeto enviadas pela CONTRATADA; caso isso não aconteça, será de responsabilidade do CONTRATANTE arcar com as consequências do atraso do seu projeto.

**Cláusula 6ª.** Caso o cronograma se estenda por motivos de atrasos na entrega do material por parte do CONTRATANTE, a CONTRATADA fica desobrigada quanto ao prazo previamente estipulado.

**Cláusula 7ª.** Será considerado abandono do projeto a ausência de respostas pontuais no prazo de 45 (quarenta e cinco) dias úteis sem aviso prévio.

**Cláusula 8ª.** No serviço estabelecido neste contrato, a CONTRATADA somente fornecerá a mão de obra necessária, responsabilizando-se o CONTRATANTE pelo fornecimento de todos os materiais para a confecção da Loja Virtual, *como imagens, logomarca e textos*.

**Cláusula 9ª.** Sendo necessários a digitalização de imagens em alta resolução, produção de conteúdo sobre a empresa, conversão de arquivos, digitação de textos ou qualquer outro serviço não exposto neste contrato, serão cobrados valores à parte, mediante aprovação prévia do cliente, como serviços complementares.

**Cláusula 10ª.** A construção da Loja Virtual será feita pessoalmente pela CONTRATADA, facultando-lhe a contratação de ajudantes, os quais terão vínculo único e direto com ela, que ficará exclusivamente responsável pelo pagamento e todos os encargos existentes.

**Cláusula 11ª.** A CONTRATADA terá completa e irrestrita liberdade para executar seu trabalho, não necessitando predeterminar horários ou funções, ficando assim caracterizado que ela exerce de maneira autônoma seus serviços, não mantendo nenhum vínculo trabalhista com o CONTRATANTE.

# Da hospedagem e do domínio

**Cláusula 12ª.** O servidor de hospedagem será de responsabilidade da CONTRATADA e suas obrigações atreladas ao plano escolhido pelo CONTRATANTE e descritas abaixo:

- Servidor de hospedagem
- {{quantidade_emails}} contas de e-mail
- Backup da Loja Virtual
- Atualizações da plataforma e dos plugins
- Renovação automática dos plugins Pro
- Restauração de backup
- Possíveis correções
- Recorrência mensal

# Do pagamento

**Cláusula 13ª.** Pelo serviço prestado, o CONTRATANTE pagará à CONTRATADA a quantia de {{contrato.valor}} ({{contrato.valor_extenso}}), em {{parcelas}} parcelas de {{valor_parcela}} sem juros no ato da assinatura deste contrato, e mensalmente a quantia de {{valor_mensal}}, via {{forma_pagamento}}, com vencimento todo dia {{dia_vencimento}} de cada mês, com início no mês de {{mes_inicio}}, por um período de {{periodo_meses}} meses, sendo renovado automaticamente caso não seja informado o cancelamento.

**Cláusula 14ª.** Caso o valor acertado da cláusula anterior não seja pago no período previsto, o CONTRATANTE se responsabilizará por multa de {{percentual_multa}}% do valor.

**Cláusula 15ª.** O CONTRATANTE concorda que o não pagamento do serviço prestado dentro de 60 (sessenta) dias, após o vencimento da primeira notificação à CONTRATADA, acarretará suspensão dos serviços, retirada da Loja Virtual do ar e/ou notificação e/ou interpelação judicial ou extrajudicial da cobrança e negativação do nome nos órgãos competentes.

# Da rescisão

**Cláusula 16ª.** Para cancelamento do contrato, uma das partes deve notificar a outra com {{prazo_aviso}} dias de antecedência, mediante confirmação da outra parte.

**§2º.** Caso o CONTRATANTE queira rescindir unilateralmente o presente contrato, após a execução das etapas previstas na Cláusula 2ª e parágrafos, o CONTRATANTE deverá fazer a quitação das parcelas vencidas e/ou vincendas previstas na Cláusula 13ª do presente contrato, em favor da CONTRATADA.

**Cláusula 17ª.** No caso de rescisão por parte da CONTRATADA, por encontrar-se impossibilitada de cumprir o presente contrato ou por motivos alheios, deverá indicar outro profissional qualificado para concluir a prestação do serviço ou, então, ressarcir ao CONTRATANTE os valores já pagos.

**Cláusula 18ª.** Na hipótese de calamidade pública, pandemia, estado de emergência ou situação semelhante que impossibilite a execução deste instrumento, seja por determinação de ente público ou por incapacidade de qualquer uma das partes, fica ajustado que as obrigações do presente contrato poderão ser suspensas ou o mesmo poderá ser rescindido, sem qualquer ônus para as partes, a critério da CONTRATADA.

**Cláusula 19ª.** Na hipótese de o CONTRATANTE não cumprir prazos e/ou atrasar reiteradamente o envio de informações cruciais para a execução dos trabalhos, objeto do presente contrato, a CONTRATADA deverá notificar por escrito o eventual atraso e, permanecendo o CONTRATANTE no atraso, fica a CONTRATADA autorizada a rescindir unilateralmente o contrato sem que haja quaisquer devoluções de valores eventualmente pagos anteriormente.

# Das disposições gerais

**Cláusula 20ª.** O CONTRATANTE é livre para sugerir todo e qualquer conteúdo informativo da sua Loja Virtual, sendo ele integralmente responsável pelos efeitos provenientes destas informações, respondendo civil e criminalmente por atos contrários à lei, propaganda enganosa, atos obscenos e violação de direitos autorais.

**Cláusula 21ª.** O CONTRATANTE terá um suporte de 30 (trinta) dias a contar da data do lançamento ou entrega do seu projeto, para alterações e ajustes técnicos, não inclusos ajustes de *layout*. Após os 30 (trinta) dias, qualquer alteração será orçada à parte.

**Cláusula 22ª.** O CONTRATANTE deverá estar ciente de que a CONTRATADA somente realizará os itens desejados que constarem no contrato. Qualquer pedido adicional será cobrado separadamente do documento, mediante prévia formulação de orçamento e aceite das partes.

# Do foro

**Cláusula 23ª.** Para dirimir quaisquer controvérsias oriundas do contrato, as partes elegem o foro da comarca da cidade de São Paulo – SP.

Por estarem assim justos e contratados, firmam o presente instrumento, em duas vias de igual teor.

{{data.cidade_data}}.
CONTRATO;
    }
}
