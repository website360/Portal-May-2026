<?php

/*
 * Quem contrata: os dados da agência que entram em todo contrato gerado.
 *
 * Ficam em config, e não no banco, porque não mudam com o uso do sistema — e
 * porque um contrato já gerado guarda o texto final, então mexer aqui só afeta
 * os próximos.
 */
return [
    'agencia' => [
        'nome' => env('CONTRATO_AGENCIA_NOME', 'AGÊNCIA MAY SERVICOS DE INFORMACAO NA INTERNET LTDA'),
        'documento' => env('CONTRATO_AGENCIA_DOCUMENTO', '40881499000108'),
        'endereco' => env(
            'CONTRATO_AGENCIA_ENDERECO',
            'Rua Dentista Barreto, 1321 – Sala 02 – Vila Carrão - São Paulo/SP – CEP 03420-000'
        ),
        'cidade' => env('CONTRATO_AGENCIA_CIDADE', 'São Paulo'),

        // Quem assina pela agência, para a qualificação das partes.
        'representante' => env('CONTRATO_AGENCIA_REPRESENTANTE', 'Caio Lima Francisco'),
        'representante_cpf' => env('CONTRATO_AGENCIA_REPRESENTANTE_CPF', '35589783828'),
        'representante_rg' => env('CONTRATO_AGENCIA_REPRESENTANTE_RG', '33.766.979-X'),
    ],

    // Prefixo da numeração: MAY-2026-0001.
    'prefixo' => env('CONTRATO_PREFIXO', 'MAY'),

    /*
     * Papel timbrado: a arte de fundo repetida em todas as páginas.
     *
     * Quando existe, ela substitui o cabeçalho desenhado — a marca, a régua e o
     * rodapé já vêm na arte, e desenhar por cima duplicaria. PNG ou JPG na
     * proporção A4 (210×297mm); 1240×1754 px cobre 150 dpi com folga.
     */
    'timbrado' => env('CONTRATO_TIMBRADO', 'storage/app/contratos/timbrado.png'),

    /*
     * Largura, em pixels, da cópia do timbrado que entra no PDF.
     *
     * 1240 px cobre a folha A4 a 150 dpi, que é mais do que um fundo precisa.
     * A arte original costuma vir em 300 dpi, e redimensioná-la a cada página
     * triplica o tempo de geração. A cópia reduzida é feita uma vez e guardada.
     */
    'timbrado_largura' => env('CONTRATO_TIMBRADO_LARGURA', 1240),

    /*
     * Logo do cabeçalho desenhado. Só é usada quando não há timbrado.
     */
    'logo' => env('CONTRATO_LOGO', 'storage/app/contratos/logo.png'),

    /*
     * As margens do texto dentro da página.
     *
     * Precisam abrir espaço para o cabeçalho e o rodapé do timbrado — cada arte
     * tem a sua área livre, então isto é ajuste fino de quem desenhou o papel.
     */
    'margens' => [
        'topo' => env('CONTRATO_MARGEM_TOPO', '45mm'),
        'direita' => env('CONTRATO_MARGEM_DIREITA', '22mm'),
        'base' => env('CONTRATO_MARGEM_BASE', '26mm'),
        'esquerda' => env('CONTRATO_MARGEM_ESQUERDA', '22mm'),
    ],

    /*
     * A tipografia do contrato.
     *
     * Coloque os arquivos .ttf na pasta abaixo com estes nomes:
     *
     *   regular.ttf  ·  bold.ttf  ·  italic.ttf  ·  bolditalic.ttf
     *
     * O negrito precisa do próprio arquivo: sem bold.ttf o gerador de PDF não
     * engrossa a fonte, ele apenas ignora o pedido — e as cláusulas saem todas
     * com o mesmo peso. Sem nenhum arquivo, cai na DejaVu Sans, que acompanha
     * a biblioteca e tem a acentuação completa.
     */
    'fonte' => [
        'nome' => env('CONTRATO_FONTE_NOME', 'Contrato'),
        'pasta' => env('CONTRATO_FONTE_PASTA', 'storage/app/contratos/fontes'),
        'tamanho' => env('CONTRATO_FONTE_TAMANHO', '10.5pt'),
        'entrelinha' => env('CONTRATO_FONTE_ENTRELINHA', '1.65'),
    ],
];
