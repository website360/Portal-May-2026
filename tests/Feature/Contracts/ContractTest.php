<?php

namespace Tests\Feature\Contracts;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\User;
use App\Support\ContractDocument;
use App\Support\ContractExpiryAlert;
use App\Support\ContractMarkup;
use App\Support\ContractPlaceholders;
use App\Support\ContractTypography;
use App\Support\Documents;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/contratos';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    // ── Marcadores ───────────────────────────────────────────────────────────

    /**
     * A ideia central do gerador: o modelo declara o que precisa, e o que o
     * sistema não conhece vira campo do formulário.
     */
    public function test_a_template_declares_the_fields_it_needs(): void
    {
        $modelo = ContractTemplate::factory()->create([
            'body' => 'Cliente {{cliente.nome}} paga {{contrato.valor}} com aviso de {{prazo_aviso}} dias, na {{plataforma_usada}}.',
        ]);

        $this->assertSame(['prazo_aviso', 'plataforma_usada'], $modelo->customPlaceholders());
    }

    public function test_the_placeholders_are_filled_from_the_client(): void
    {
        $cliente = Client::factory()->create([
            'name' => 'Box Locadora Ltda',
            'trade_name' => 'Box Locadora',
            'document' => '12345678000199',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $contrato = Contract::factory()->create(['client_id' => $cliente->id, 'value' => 1500]);

        $valores = ContractPlaceholders::valuesFor($contrato);

        $this->assertSame('Box Locadora', $valores['cliente.nome']);
        $this->assertSame('Box Locadora Ltda', $valores['cliente.razao_social']);
        $this->assertSame('12.345.678/0001-99', $valores['cliente.documento']);
        $this->assertSame('R$ 1.500,00', $valores['contrato.valor']);
        $this->assertSame('mil e quinhentos reais', $valores['contrato.valor_extenso']);
    }

    /**
     * Marcador sem valor fica visível no texto.
     *
     * Um espaço em branco passa despercebido e vai assinado assim; "{{prazo}}"
     * escrito no meio do contrato denuncia o que faltou.
     */
    public function test_an_unfilled_placeholder_stays_visible(): void
    {
        $texto = ContractPlaceholders::render(
            'Paga {{contrato.valor}} em {{forma_de_pagamento}}.',
            ['contrato.valor' => 'R$ 500,00']
        );

        $this->assertSame('Paga R$ 500,00 em {{forma_de_pagamento}}.', $texto);
    }

    public function test_the_placeholder_accepts_spaces_inside(): void
    {
        $this->assertSame(
            'Olá Ana',
            ContractPlaceholders::render('Olá {{ cliente.responsavel }}', ['cliente.responsavel' => 'Ana'])
        );
    }

    // ── Valor por extenso ────────────────────────────────────────────────────

    public function test_the_amount_is_written_the_way_a_contract_writes_it(): void
    {
        /*
         * Lista de pares, e não mapa: chave de array em PHP não pode ser float,
         * então 0.5 e 0.0 virariam a mesma chave 0 e um caso sumiria em silêncio
         * — foi o que aconteceu na primeira versão deste teste.
         */
        $casos = [
            [0.0, 'zero real'],
            [0.5, 'cinquenta centavos'],
            [1.0, 'um real'],
            [1.99, 'um real e noventa e nove centavos'],
            [15.0, 'quinze reais'],
            [100.0, 'cem reais'],
            [101.0, 'cento e um reais'],
            [1000.0, 'mil reais'],
            // Centena redonda no fim pede "e"…
            [1500.0, 'mil e quinhentos reais'],
            [1015.0, 'mil e quinze reais'],
            // …e um grupo que já usa "e" por dentro pede vírgula.
            [1230.0, 'mil, duzentos e trinta reais'],
            [2000.0, 'dois mil reais'],
            [1000000.0, 'um milhão de reais'],
            [1500000.0, 'um milhão e quinhentos mil reais'],
        ];

        foreach ($casos as [$valor, $esperado]) {
            $this->assertSame($esperado, Money::inWords($valor), "valor: {$valor}");
        }
    }

    /** Centavos que o float não representa exato não podem sumir. */
    public function test_cents_survive_the_float(): void
    {
        $this->assertSame('onze reais e dez centavos', Money::inWords(11.10));
        $this->assertSame('zero real', Money::inWords(0.0));
    }

    public function test_documents_are_formatted_by_their_length(): void
    {
        $this->assertSame('12.345.678/0001-99', Documents::format('12345678000199'));
        $this->assertSame('123.456.789-09', Documents::format('12345678909'));
        // Nem CPF nem CNPJ: devolve como veio, sem inventar formato.
        $this->assertSame('123', Documents::format('123'));
    }

    // ── Marcação do texto ────────────────────────────────────────────────────

    public function test_the_markup_covers_what_a_contract_needs(): void
    {
        $html = ContractMarkup::toHtml("# Cláusula\n\nTexto com **negrito**.\n\n- um\n- dois\n\n---");

        $this->assertStringContainsString('<h2>Cláusula</h2>', $html);
        $this->assertStringContainsString('<strong>negrito</strong>', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>dois</li>', $html);
        $this->assertStringContainsString('<hr>', $html);
    }

    /** Um "<" digitado no contrato não pode virar etiqueta no PDF. */
    public function test_the_markup_escapes_what_the_user_typed(): void
    {
        $html = ContractMarkup::toHtml('Multa de <script>alert(1)</script> por atraso.');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /** O cronograma de etapas do contrato de loja virtual precisa de tabela. */
    public function test_the_markup_builds_tables(): void
    {
        $html = ContractMarkup::toHtml("| Módulo | Ação | Prazo |\n| --- | --- | --- |\n| 01 | Criação do layout | 10 dias |");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Módulo</th>', $html);
        $this->assertStringContainsString('<td>Criação do layout</td>', $html);
        $this->assertStringContainsString('</table>', $html);
        // A linha de tracinhos é separador, não conteúdo.
        $this->assertStringNotContainsString('---', $html);
        $this->assertSame(2, substr_count($html, '<tr>'));
    }

    /** Uma tabela colada num parágrafo não pode engolir o texto seguinte. */
    public function test_a_table_closes_before_the_next_paragraph(): void
    {
        $html = ContractMarkup::toHtml("| a | b |\n| --- | --- |\n| 1 | 2 |\nTexto depois.");

        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('<p>Texto depois.</p>', $html);
        $this->assertLessThan(strpos($html, '<p>'), strpos($html, '</table>'));
    }

    /**
     * A qualificação das partes abre todo contrato e é onde mais se erra
     * copiando à mão. Por isso é um marcador só, montado pelo sistema.
     */
    public function test_the_client_qualification_is_written_like_a_contract(): void
    {
        $cliente = Client::factory()->create([
            'name' => '62.710.697 ADRIANA MARIA DOS SANTOS VEIGAS',
            'document' => '62710697000141',
            'representative_name' => 'Adriana Maria dos Santos Veigas',
            'representative_role' => 'sócia administradora',
            'representative_document' => '25897738896',
            'street' => 'Rua Domiciano Leite Ribeiro',
            'number' => '802',
            'district' => 'Vila Guarani',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '04317-000',
        ]);

        $texto = ContractPlaceholders::valuesFor(Contract::factory()->create(['client_id' => $cliente->id]))['cliente.qualificacao'];

        $this->assertStringContainsString('62.710.697/0001-41', $texto);
        $this->assertStringContainsString('representada pelo(a) sócia administradora Adriana Maria dos Santos Veigas', $texto);
        $this->assertStringContainsString('258.977.388-96', $texto);
        $this->assertStringContainsString('Vila Guarani', $texto);
    }

    /** Sem representante cadastrado, o contato do dia a dia entra no lugar. */
    public function test_the_qualification_falls_back_to_the_contact(): void
    {
        $cliente = Client::factory()->create([
            'document' => '12345678000199',
            'contact_name' => 'João',
            'contact_role' => 'diretor',
            'representative_name' => null,
        ]);

        $texto = ContractPlaceholders::valuesFor(Contract::factory()->create(['client_id' => $cliente->id]))['cliente.qualificacao'];

        $this->assertStringContainsString('representada pelo(a) diretor João', $texto);
    }

    /** Pessoa física assina por si: não há representante a declarar. */
    public function test_a_person_has_no_representative_clause(): void
    {
        $cliente = Client::factory()->create([
            'type' => 'person',
            'document' => '12345678909',
            'contact_name' => 'Maria',
        ]);

        $texto = ContractPlaceholders::valuesFor(Contract::factory()->create(['client_id' => $cliente->id]))['cliente.qualificacao'];

        $this->assertStringContainsString('inscrito(a) no CPF/MF sob o nº 123.456.789-09', $texto);
        $this->assertStringNotContainsString('representada', $texto);
    }

    public function test_the_agency_qualification_comes_from_the_config(): void
    {
        config([
            'contratos.agencia.nome' => 'AGÊNCIA MAY LTDA',
            'contratos.agencia.documento' => '40881499000108',
            'contratos.agencia.representante' => 'Caio Lima Francisco',
            'contratos.agencia.representante_cpf' => '35589783828',
            'contratos.agencia.representante_rg' => '33.766.979-X',
        ]);

        $texto = ContractPlaceholders::valuesFor(Contract::factory()->create())['agencia.qualificacao'];

        $this->assertStringContainsString('40.881.499/0001-08', $texto);
        $this->assertStringContainsString('neste ato representada pelo sócio administrador Caio Lima Francisco', $texto);
        $this->assertStringContainsString('RG nº 33.766.979-X', $texto);
        $this->assertStringContainsString('355.897.838-28', $texto);
    }

    /** O modelo decide se o PDF leva linha de assinatura. */
    public function test_the_signature_block_follows_the_template(): void
    {
        $semAssinatura = ContractTemplate::factory()->create(['with_signatures' => false]);
        $comAssinatura = ContractTemplate::factory()->create(['with_signatures' => true]);

        $clicksign = Contract::factory()->create(['contract_template_id' => $semAssinatura->id]);
        $impresso = Contract::factory()->create(['contract_template_id' => $comAssinatura->id]);

        $this->assertStringNotContainsString('Contratante', (new ContractDocument($clicksign))->html());
        $this->assertStringContainsString('Contratante', (new ContractDocument($impresso))->html());
    }
    // ── Tipografia e timbrado ────────────────────────────────────────────────

    /**
     * Os arquivos de arte não podem morar em public/.
     *
     * public/ é a raiz do site: uma pasta chamada "contratos" ali é encontrada
     * pelo servidor antes da rota, e /contratos passa a responder 404 — foi
     * exatamente o que aconteceu quando a pasta nasceu lá. Como bônus, fora de
     * public/ o timbrado e as fontes deixam de ser baixáveis por qualquer um.
     */
    public function test_the_artwork_lives_outside_the_web_root(): void
    {
        $caminhos = [
            'timbrado' => config('contratos.timbrado'),
            'logo' => config('contratos.logo'),
            'fontes' => config('contratos.fonte.pasta'),
        ];

        foreach ($caminhos as $nome => $caminho) {
            $this->assertStringStartsNotWith('public/', (string) $caminho, "{$nome} não pode ficar em public/");
        }
    }

    /** E nenhuma pasta de public/ pode ter o nome de um módulo. */
    public function test_no_folder_in_public_shadows_a_module(): void
    {
        $modulos = array_keys(Permissions::MODULES);

        foreach (glob(public_path('*'), GLOB_ONLYDIR) ?: [] as $pasta) {
            $this->assertNotContains(
                basename($pasta),
                $modulos,
                'A pasta public/'.basename($pasta).' esconde a rota de mesmo nome.'
            );
        }
    }

    /**
     * Sem arquivo de fonte, cai na que acompanha a biblioteca.
     *
     * Declarar a família própria sem ter o arquivo faria o texto cair numa
     * fonte qualquer do sistema, e o PDF sairia diferente em cada máquina.
     */
    public function test_without_font_files_it_falls_back(): void
    {
        config(['contratos.fonte.pasta' => 'pasta/que/nao/existe']);

        $art = new ContractTypography;

        $this->assertSame('"DejaVu Sans", sans-serif', $art->family());
        $this->assertSame('', $art->fontFaces());
        $this->assertSame([], $art->missingFaces());
    }

    /** Com os arquivos, cada peso vira uma regra @font-face. */
    public function test_the_font_files_become_font_faces(): void
    {
        $pasta = 'storage/framework/testing/fontes';
        $absoluta = base_path($pasta);

        $this->limparPasta($absoluta);
        mkdir($absoluta, 0755, true);
        file_put_contents("{$absoluta}/regular.ttf", 'x');
        file_put_contents("{$absoluta}/bold.ttf", 'x');

        config(['contratos.fonte.pasta' => $pasta, 'contratos.fonte.nome' => 'Contrato']);

        $art = new ContractTypography;

        $this->assertStringContainsString('"Contrato"', $art->family());
        $this->assertSame(2, substr_count($art->fontFaces(), '@font-face'));
        $this->assertStringContainsString('font-weight: bold', $art->fontFaces());

        // Avisa o que falta: sem o arquivo, o peso simplesmente não acontece.
        $this->assertSame(['italic', 'bolditalic'], $art->missingFaces());

        $this->limparPasta($absoluta);
    }

    /**
     * O timbrado substitui o cabeçalho desenhado.
     *
     * A marca e a régua já vêm na arte; desenhar por cima duplicaria.
     */
    public function test_the_letterhead_replaces_the_drawn_header(): void
    {
        $arquivo = 'storage/framework/testing/timbrado.png';
        $absoluto = base_path($arquivo);

        @mkdir(dirname($absoluto), 0755, true);
        file_put_contents($absoluto, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        config(['contratos.timbrado' => $arquivo]);

        $html = (new ContractDocument(Contract::factory()->create()))->html();

        $this->assertStringContainsString('class="timbrado"', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        // z-index negativo: sem ele a arte cobre o texto e a página sai em branco.
        $this->assertStringContainsString('z-index: -1', $html);
        $this->assertStringNotContainsString('<header>', $html);

        unlink($absoluto);
    }

    /** Sem timbrado, o cabeçalho desenhado entra no lugar. */
    public function test_without_a_letterhead_the_header_is_drawn(): void
    {
        config(['contratos.timbrado' => 'nao/existe.png', 'contratos.logo' => 'nao/existe.png']);

        $html = (new ContractDocument(Contract::factory()->create()))->html();

        $this->assertStringContainsString('<header>', $html);
        $this->assertStringNotContainsString('class="timbrado"', $html);
    }

    private function limparPasta(string $pasta): void
    {
        if (! is_dir($pasta)) {
            return;
        }

        foreach (glob("{$pasta}/*") ?: [] as $arquivo) {
            unlink($arquivo);
        }

        rmdir($pasta);
    }

    // ── Numeração ────────────────────────────────────────────────────────────

    public function test_the_numbering_runs_per_year(): void
    {
        $ano = Carbon::today()->year;
        $prefixo = config('contratos.prefixo');

        $this->assertSame("{$prefixo}-{$ano}-0001", Contract::nextNumber());

        Contract::factory()->create(['number' => "{$prefixo}-{$ano}-0001"]);
        Contract::factory()->create(['number' => "{$prefixo}-{$ano}-0002"]);

        $this->assertSame("{$prefixo}-{$ano}-0003", Contract::nextNumber());

        // O ano anterior não empurra a sequência do corrente.
        Contract::factory()->create(['number' => $prefixo.'-'.($ano - 1).'-0009']);

        $this->assertSame("{$prefixo}-{$ano}-0003", Contract::nextNumber());
    }

    // ── Geração ──────────────────────────────────────────────────────────────

    public function test_generating_a_contract_freezes_the_text(): void
    {
        $cliente = Client::factory()->create(['trade_name' => 'Box Locadora', 'document' => '12345678000199']);
        $modelo = ContractTemplate::factory()->create([
            'name' => 'Hospedagem + Manutenção',
            'body' => 'Cliente {{cliente.nome}}, CNPJ {{cliente.documento}}, paga {{contrato.valor}}. Aviso: {{prazo_aviso}} dias.',
        ]);

        $this->post(self::URL, [
            'client_id' => $cliente->id,
            'contract_template_id' => $modelo->id,
            'title' => 'Prestação de serviços',
            'service' => $modelo->name,
            'value' => 500,
            'starts_at' => '2026-08-01',
            'variables' => ['prazo_aviso' => '30'],
        ])->assertSessionHasNoErrors();

        $contrato = Contract::sole();

        $this->assertStringContainsString('Box Locadora', $contrato->body);
        $this->assertStringContainsString('12.345.678/0001-99', $contrato->body);
        $this->assertStringContainsString('R$ 500,00', $contrato->body);
        $this->assertStringContainsString('Aviso: 30 dias', $contrato->body);
    }

    /**
     * O texto guardado é o contrato. Editar o modelo depois não pode reescrever
     * o que já foi gerado.
     */
    public function test_editing_the_template_does_not_rewrite_what_was_generated(): void
    {
        $modelo = ContractTemplate::factory()->create(['body' => 'Versão original.']);
        $contrato = Contract::factory()->create(['contract_template_id' => $modelo->id, 'body' => 'Versão original.']);

        $modelo->update(['body' => 'Versão nova.']);

        $this->assertSame('Versão original.', $contrato->refresh()->body);
    }

    /** E excluir o modelo não leva os contratos junto. */
    public function test_deleting_a_template_keeps_the_contracts(): void
    {
        $modelo = ContractTemplate::factory()->create();
        $contrato = Contract::factory()->create(['contract_template_id' => $modelo->id]);

        $this->delete("/configuracoes/modelos-de-contrato/{$modelo->id}")->assertSessionHasNoErrors();

        $contrato->refresh();

        $this->assertNotNull($contrato->id);
        $this->assertNull($contrato->contract_template_id);
        $this->assertSame('Texto do contrato.', $contrato->body);
    }

    /** Assinado, o texto não é mais regerado — o papel assinado é o que vale. */
    public function test_a_signed_contract_is_not_regenerated(): void
    {
        $modelo = ContractTemplate::factory()->create(['body' => 'Valor: {{contrato.valor}}.']);
        $contrato = Contract::factory()->create([
            'contract_template_id' => $modelo->id,
            'body' => 'Valor: R$ 500,00.',
            'signed_at' => '2026-08-01',
            'value' => 500,
        ]);

        $this->put(self::URL."/{$contrato->id}", [
            'client_id' => $contrato->client_id,
            'contract_template_id' => $modelo->id,
            'title' => $contrato->title,
            'service' => $contrato->service,
            'value' => 900,
            'starts_at' => $contrato->starts_at->toDateString(),
            'signed_at' => '2026-08-01',
        ])->assertSessionHasNoErrors();

        $contrato->refresh();

        $this->assertSame('900.00', $contrato->value);
        // O valor do cadastro mudou; o texto assinado, não.
        $this->assertSame('Valor: R$ 500,00.', $contrato->body);
    }

    public function test_a_draft_is_regenerated_when_edited(): void
    {
        $modelo = ContractTemplate::factory()->create(['body' => 'Valor: {{contrato.valor}}.']);
        $contrato = Contract::factory()->draft()->create(['contract_template_id' => $modelo->id, 'value' => 500]);

        $this->put(self::URL."/{$contrato->id}", [
            'client_id' => $contrato->client_id,
            'contract_template_id' => $modelo->id,
            'title' => $contrato->title,
            'service' => $contrato->service,
            'value' => 900,
            'starts_at' => $contrato->starts_at->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Valor: R$ 900,00.', $contrato->refresh()->body);
    }

    // ── Situação ─────────────────────────────────────────────────────────────

    public function test_the_situation_comes_from_the_dates_and_the_signature(): void
    {
        $this->assertSame(Contract::STATUS_DRAFT, Contract::factory()->draft()->create()->status());
        $this->assertSame(Contract::STATUS_ACTIVE, Contract::factory()->create()->status());
        $this->assertSame(Contract::STATUS_EXPIRING, Contract::factory()->endingIn(10)->create()->status());
        $this->assertSame(Contract::STATUS_ENDED, Contract::factory()->endingIn(-1)->create()->status());
        // Prazo indeterminado é vigente, não vencido.
        $this->assertSame(Contract::STATUS_ACTIVE, Contract::factory()->create(['ends_at' => null])->status());
    }

    /** O filtro em SQL tem de concordar com o status() em PHP. */
    public function test_the_filter_agrees_with_the_status(): void
    {
        $rascunho = Contract::factory()->draft()->create();
        $vigente = Contract::factory()->create();
        $vencendo = Contract::factory()->endingIn(10)->create();
        $encerrado = Contract::factory()->endingIn(-5)->create();
        $cancelado = Contract::factory()->create();
        $cancelado->update(['cancelled_at' => now()]);

        $esperado = [
            'draft' => $rascunho,
            'active' => $vigente,
            'expiring' => $vencendo,
            'ended' => $encerrado,
            'cancelled' => $cancelado,
        ];

        foreach ($esperado as $status => $contrato) {
            $this->assertSame($status, $contrato->fresh()->status(), "status() de {$status}");

            $ids = collect($this->get(self::URL."?statuses[]={$status}")->viewData('page')['props']['contracts']['data'])->pluck('id');

            $this->assertSame([$contrato->id], $ids->all(), "filtro {$status}");
        }
    }

    public function test_cancelling_does_not_delete(): void
    {
        $contrato = Contract::factory()->create();

        $this->post(self::URL."/{$contrato->id}/cancelamento")->assertSessionHasNoErrors();

        $this->assertSame(Contract::STATUS_CANCELLED, $contrato->refresh()->status());
        $this->assertSame(1, Contract::count());

        // E dá para voltar atrás.
        $this->post(self::URL."/{$contrato->id}/cancelamento");

        $this->assertNull($contrato->refresh()->cancelled_at);
    }

    public function test_a_signature_cannot_be_dated_in_the_future(): void
    {
        $contrato = Contract::factory()->draft()->create();

        $this->post(self::URL."/{$contrato->id}/assinatura", ['signed_at' => Carbon::tomorrow()->toDateString()])
            ->assertSessionHasErrors('signed_at');

        $this->assertNull($contrato->refresh()->signed_at);
    }

    public function test_the_end_cannot_be_before_the_start(): void
    {
        $cliente = Client::factory()->create();

        $this->post(self::URL, [
            'client_id' => $cliente->id,
            'title' => 'Teste',
            'service' => 'Serviço',
            'starts_at' => '2026-08-10',
            'ends_at' => '2026-08-01',
        ])->assertSessionHasErrors('ends_at');

        $this->assertSame(0, Contract::count());
    }

    // ── PDF e arquivo ────────────────────────────────────────────────────────

    public function test_the_generated_pdf_comes_out(): void
    {
        $contrato = Contract::factory()->create(['body' => "# Cláusula primeira\n\nO objeto é a prestação de serviços."]);

        $resposta = $this->get(self::URL."/{$contrato->id}/pdf");

        $resposta->assertOk();
        // A dompdf devolve resposta comum, não streamed.
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    /** Anexado o assinado, é ele que baixa — não o texto gerado. */
    public function test_the_attachment_wins_over_the_generated_text(): void
    {
        Storage::fake('public');

        $contrato = Contract::factory()->create();

        $this->put(self::URL."/{$contrato->id}", [
            'client_id' => $contrato->client_id,
            'title' => $contrato->title,
            'service' => $contrato->service,
            'starts_at' => $contrato->starts_at->toDateString(),
            'pdf' => UploadedFile::fake()->create('assinado.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $contrato->refresh();

        $this->assertTrue($contrato->hasAttachment());
        Storage::disk('public')->assertExists($contrato->pdf_path);
    }

    public function test_only_a_pdf_is_accepted(): void
    {
        Storage::fake('public');

        $contrato = Contract::factory()->create();

        $this->put(self::URL."/{$contrato->id}", [
            'client_id' => $contrato->client_id,
            'title' => $contrato->title,
            'service' => $contrato->service,
            'starts_at' => $contrato->starts_at->toDateString(),
            'pdf' => UploadedFile::fake()->create('contrato.docx', 100),
        ])->assertSessionHasErrors('pdf');
    }

    /** Trocar o anexo não pode deixar o antigo ocupando disco. */
    public function test_replacing_the_attachment_removes_the_old_one(): void
    {
        Storage::fake('public');

        $contrato = Contract::factory()->create(['pdf_path' => 'contratos/antigo.pdf']);
        Storage::disk('public')->put('contratos/antigo.pdf', 'conteudo');

        $this->put(self::URL."/{$contrato->id}", [
            'client_id' => $contrato->client_id,
            'title' => $contrato->title,
            'service' => $contrato->service,
            'starts_at' => $contrato->starts_at->toDateString(),
            'pdf' => UploadedFile::fake()->create('novo.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('contratos/antigo.pdf');
        Storage::disk('public')->assertExists($contrato->refresh()->pdf_path);
    }

    public function test_removing_the_file_keeps_the_contract(): void
    {
        Storage::fake('public');

        $contrato = Contract::factory()->create(['pdf_path' => 'contratos/x.pdf']);
        Storage::disk('public')->put('contratos/x.pdf', 'conteudo');

        $this->delete(self::URL."/{$contrato->id}/arquivo")->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('contratos/x.pdf');
        $this->assertSame(1, Contract::count());
        $this->assertFalse($contrato->refresh()->hasAttachment());
    }

    /** Excluir o contrato leva o arquivo junto — não deixa órfão no disco. */
    public function test_deleting_the_contract_takes_the_file(): void
    {
        Storage::fake('public');

        $contrato = Contract::factory()->create(['pdf_path' => 'contratos/y.pdf']);
        Storage::disk('public')->put('contratos/y.pdf', 'conteudo');

        $this->delete(self::URL."/{$contrato->id}")->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('contratos/y.pdf');
        $this->assertSame(0, Contract::count());
    }

    // ── Telas e permissão ────────────────────────────────────────────────────

    public function test_the_generator_offers_only_active_templates(): void
    {
        ContractTemplate::factory()->create(['name' => 'Ativo']);
        ContractTemplate::factory()->inactive()->create(['name' => 'Desativado']);

        $this->get(self::URL.'/gerar')->assertOk()->assertInertia(
            fn ($page) => $page->component('contratos/gerar')
                ->has('templates', 1)
                ->where('templates.0.name', 'Ativo')
        );
    }

    public function test_the_generator_sends_the_fields_each_template_asks_for(): void
    {
        ContractTemplate::factory()->create(['body' => 'Aviso de {{prazo_aviso}} dias para {{cliente.nome}}.']);

        $this->get(self::URL.'/gerar')->assertInertia(
            fn ($page) => $page->where('templates.0.fields.0.key', 'prazo_aviso')
                ->where('templates.0.fields.0.label', 'Prazo aviso')
                ->has('templates.0.fields', 1)
        );
    }

    /**
     * A prévia sai do mesmo código do contrato de verdade.
     *
     * É a razão de ela vir do servidor: calculá-la na tela exigiria repetir a
     * formatação de CNPJ e o valor por extenso em TypeScript, e as duas versões
     * divergiriam no primeiro detalhe.
     */
    public function test_the_preview_matches_what_will_be_generated(): void
    {
        $cliente = Client::factory()->create(['trade_name' => 'Box Locadora', 'document' => '12345678000199']);
        $modelo = ContractTemplate::factory()->create([
            'body' => '{{cliente.nome}}, CNPJ {{cliente.documento}}, paga {{contrato.valor}} ({{contrato.valor_extenso}}) em {{forma}}.',
        ]);

        $previa = $this->getJson(self::URL.'/previa?'.http_build_query([
            'contract_template_id' => $modelo->id,
            'client_id' => $cliente->id,
            'value' => 1500,
            'variables' => ['forma' => 'boleto'],
        ]))->assertOk()->json('body');

        $this->post(self::URL, [
            'client_id' => $cliente->id,
            'contract_template_id' => $modelo->id,
            'title' => 'Teste',
            'service' => $modelo->name,
            'value' => 1500,
            'starts_at' => '2026-08-01',
            'variables' => ['forma' => 'boleto'],
        ])->assertSessionHasNoErrors();

        $this->assertSame($previa, Contract::sole()->body);
        $this->assertStringContainsString('12.345.678/0001-99', $previa);
        $this->assertStringContainsString('mil e quinhentos reais', $previa);
    }

    /** Sem cliente escolhido, os marcadores dele seguem à mostra. */
    public function test_the_preview_works_before_a_client_is_chosen(): void
    {
        $modelo = ContractTemplate::factory()->create(['body' => 'Para {{cliente.nome}}, aviso de {{prazo}} dias.']);

        $body = $this->getJson(self::URL.'/previa?'.http_build_query([
            'contract_template_id' => $modelo->id,
            'variables' => ['prazo' => '30'],
        ]))->assertOk()->json('body');

        $this->assertSame('Para {{cliente.nome}}, aviso de 30 dias.', $body);
    }

    public function test_someone_without_the_module_does_not_get_in(): void
    {
        $this->actingAs(User::factory()->member()->create(['permissions' => ['contratos' => 'none']]))
            ->get(self::URL)
            ->assertForbidden();
    }

    public function test_read_only_cannot_generate(): void
    {
        $cliente = Client::factory()->create();

        $this->actingAs(User::factory()->member()->create(['permissions' => ['contratos' => 'read']]))
            ->post(self::URL, [
                'client_id' => $cliente->id,
                'title' => 'Teste',
                'service' => 'Serviço',
                'starts_at' => '2026-08-01',
            ])
            ->assertSessionHasErrors('permissao');

        $this->assertSame(0, Contract::count());
    }

    // ── Cadastro direto (sem gerar documento) ────────────────────────────────

    public function test_a_contract_is_registered_without_a_document(): void
    {
        $cliente = Client::factory()->create();

        $this->post(self::URL.'/registrar', [
            'client_id' => $cliente->id,
            'title' => 'Hospedagem anual',
            'service' => 'Hospedagem',
            'value' => 1200,
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
        ])->assertRedirect(self::URL)->assertSessionHasNoErrors();

        $contrato = Contract::firstOrFail();

        $this->assertSame($cliente->id, $contrato->client_id);
        $this->assertNull($contrato->contract_template_id);
        $this->assertNull($contrato->body);
        $this->assertTrue($contrato->active_without_signature);
    }

    /** Cadastrado direto vale pela data, mesmo sem assinatura — não é rascunho. */
    public function test_a_registered_contract_is_active_without_a_signature(): void
    {
        $contrato = Contract::factory()->create([
            'signed_at' => null,
            'active_without_signature' => true,
            'ends_at' => Carbon::today()->addMonths(6),
        ]);

        $this->assertSame(Contract::STATUS_ACTIVE, $contrato->status());
    }

    /** A regra antiga segue de pé para os gerados: sem assinatura, é rascunho. */
    public function test_a_generated_contract_without_signature_is_still_a_draft(): void
    {
        $contrato = Contract::factory()->create([
            'signed_at' => null,
            'active_without_signature' => false,
        ]);

        $this->assertSame(Contract::STATUS_DRAFT, $contrato->status());
    }

    public function test_read_only_cannot_register(): void
    {
        $cliente = Client::factory()->create();

        $this->actingAs(User::factory()->member()->create(['permissions' => ['contratos' => 'read']]))
            ->post(self::URL.'/registrar', [
                'client_id' => $cliente->id,
                'title' => 'Teste',
                'service' => 'Serviço',
                'starts_at' => '2026-08-01',
            ])
            ->assertSessionHasErrors('permissao');

        $this->assertSame(0, Contract::count());
    }

    // ── Renovação ────────────────────────────────────────────────────────────

    /** Renovar estende a data de fim do mesmo contrato — não cria outro. */
    public function test_renewing_extends_the_same_contract(): void
    {
        $contrato = Contract::factory()->endingIn(-1)->create(['value' => 1000]);
        $novoFim = Carbon::today()->addYear()->toDateString();

        $this->post(self::URL."/{$contrato->id}/renovacao", ['ends_at' => $novoFim])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Contract::count());
        $this->assertSame($novoFim, $contrato->refresh()->ends_at->toDateString());
        $this->assertSame(Contract::STATUS_ACTIVE, $contrato->status());
    }

    /** Um valor novo na renovação reajusta o contrato; sem ele, o valor fica. */
    public function test_renewing_can_adjust_the_value(): void
    {
        $contrato = Contract::factory()->create(['value' => 1000]);

        $this->post(self::URL."/{$contrato->id}/renovacao", [
            'ends_at' => Carbon::today()->addYear()->toDateString(),
            'value' => 1500,
        ])->assertSessionHasNoErrors();

        $this->assertSame('1500.00', $contrato->refresh()->value);
    }

    public function test_renewing_rejects_an_end_before_the_start(): void
    {
        $contrato = Contract::factory()->create(['starts_at' => '2026-01-01']);

        $this->post(self::URL."/{$contrato->id}/renovacao", ['ends_at' => '2025-12-31'])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_read_only_cannot_renew(): void
    {
        $contrato = Contract::factory()->create();

        $this->actingAs(User::factory()->member()->create(['permissions' => ['contratos' => 'read']]))
            ->post(self::URL."/{$contrato->id}/renovacao", ['ends_at' => Carbon::today()->addYear()->toDateString()])
            ->assertSessionHasErrors('permissao');
    }

    // ── Alerta de vencimento ─────────────────────────────────────────────────

    public function test_the_alert_describes_the_contract_for_the_agency(): void
    {
        $cliente = Client::factory()->create(['name' => 'Padaria Pão Quente']);
        $contrato = Contract::factory()->create([
            'client_id' => $cliente->id,
            'number' => '0007',
            'service' => 'Hospedagem',
            'value' => 1200,
            'ends_at' => Carbon::today()->addDays(30),
        ]);

        $vars = (new ContractExpiryAlert($contrato))->variables();

        $this->assertSame('Padaria Pão Quente', $vars['cliente.nome']);
        $this->assertSame('0007', $vars['contrato.numero']);
        $this->assertSame('R$ 1.200,00', $vars['contrato.valor']);
        $this->assertSame('30', $vars['contrato.dias']);
    }

    /** Dispara nos marcos (aqui, 7 dias antes). */
    public function test_the_command_alerts_a_contract_at_a_milestone(): void
    {
        $contrato = Contract::factory()->create(['ends_at' => Carbon::today()->addDays(7)]);

        $this->artisan('contratos:avisar-vencimento --dry-run')
            ->expectsOutputToContain($contrato->number)
            ->assertSuccessful();
    }

    /** Fora dos marcos (10 dias) não avisa. */
    public function test_the_command_ignores_contracts_off_the_milestones(): void
    {
        Contract::factory()->create(['ends_at' => Carbon::today()->addDays(10)]);

        $this->artisan('contratos:avisar-vencimento --dry-run')
            ->expectsOutputToContain('Nenhum contrato em marco')
            ->assertSuccessful();
    }

    // ── Período contratado e reajuste de preço ───────────────────────────────

    public function test_a_contract_stores_period_and_price_review(): void
    {
        $cliente = Client::factory()->create();

        $this->post(self::URL.'/registrar', [
            'client_id' => $cliente->id,
            'title' => 'Hospedagem',
            'service' => 'Hospedagem',
            'starts_at' => '2026-01-01',
            'billing_period' => 'monthly',
            'price_review_at' => '2028-01-01',
            'price_review_years' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $contrato = Contract::firstOrFail();

        $this->assertSame('monthly', $contrato->billing_period);
        $this->assertSame('2028-01-01', $contrato->price_review_at->toDateString());
        $this->assertSame(2, $contrato->price_review_years);
    }

    /** O reajuste entra em "a reajustar" só dentro da janela. */
    public function test_review_is_due_only_within_the_window(): void
    {
        $this->assertTrue(Contract::factory()->create(['price_review_at' => Carbon::today()->addDays(10)])->reviewDue());
        $this->assertFalse(Contract::factory()->create(['price_review_at' => Carbon::today()->addDays(90)])->reviewDue());
        $this->assertFalse(Contract::factory()->create(['price_review_at' => null])->reviewDue());
    }

    /** O comando avisa também os reajustes nos marcos. */
    public function test_the_command_alerts_a_price_review_at_a_milestone(): void
    {
        $contrato = Contract::factory()->create(['price_review_at' => Carbon::today()->addDays(15)]);

        $this->artisan('contratos:avisar-vencimento --dry-run')
            ->expectsOutputToContain($contrato->number)
            ->expectsOutputToContain('Reajuste')
            ->assertSuccessful();
    }
}
