<?php

namespace Tests\Feature\Settings;

use App\Models\Client;
use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\MaintenanceChecklist;
use App\Support\MaintenanceReport;
use App\Support\MessageComposer;
use App\Support\MessageRules;
use App\Support\MessageTriggers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Os modelos de mensagem do WhatsApp.
 *
 * O que se testa aqui é a escolha: qual modelo sai, com qual texto, e o que
 * acontece quando nenhum serve. Errar isso manda a mensagem errada para o
 * cliente — e ninguém do lado de cá fica sabendo.
 */
class MessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/configuracoes/mensagens';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function modelo(array $atributos = []): MessageTemplate
    {
        return MessageTemplate::create($atributos + [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Padrão',
            'variations' => ['Olá, {{cliente.contato}}!'],
            'conditions' => [],
            'priority' => 0,
            'active' => true,
        ]);
    }

    private function manutencao(?string $notes = null, string $performed = '2026-08-17'): Maintenance
    {
        $client = Client::factory()->create(['name' => 'Padaria Pão Quente', 'contact_name' => 'Maria Souza', 'phone' => '11988887777']);
        $plan = MaintenancePlan::factory()->create(['client_id' => $client->id, 'site_url' => 'paoquente.com.br']);

        return Maintenance::create([
            'maintenance_plan_id' => $plan->id,
            'user_id' => User::factory()->create()->id,
            'performed_at' => Carbon::parse($performed),
            'items' => [
                ['key' => 'backup', 'label' => 'Backup completo', 'result' => MaintenanceChecklist::DONE],
                ['key' => 'links', 'label' => 'Links quebrados', 'result' => MaintenanceChecklist::NOT_NEEDED],
                ['key' => 'plugins', 'label' => 'Plugins', 'result' => MaintenanceChecklist::SKIPPED],
            ],
            'notes' => $notes,
        ]);
    }

    // ── Escolha do modelo ────────────────────────────────────────────────────

    /** Sem modelo cadastrado, o relatório continua saindo. */
    public function test_without_a_template_the_built_in_text_goes_out(): void
    {
        $texto = (new MaintenanceReport($this->manutencao()))->text();

        $this->assertStringContainsString('Relatório de Manutenção', $texto);
        $this->assertStringContainsString('paoquente.com.br', $texto);
        $this->assertStringContainsString('✅ Backup completo', $texto);
    }

    public function test_a_registered_template_replaces_the_built_in_text(): void
    {
        $this->modelo(['variations' => ['Oi {{cliente.contato}}, o {{site.url}} está em dia.']]);

        $this->assertSame(
            'Oi Maria Souza, o paoquente.com.br está em dia.',
            (new MaintenanceReport($this->manutencao()))->text()
        );
    }

    /** A regra específica ganha da geral, e não a ordem de cadastro. */
    public function test_the_higher_priority_wins(): void
    {
        $this->modelo(['name' => 'Geral', 'variations' => ['geral'], 'priority' => 0]);
        $this->modelo([
            'name' => 'Com observação',
            'variations' => ['especial'],
            'priority' => 10,
            'conditions' => [['field' => 'tem_observacoes', 'operator' => 'igual', 'value' => 'sim']],
        ]);

        $this->assertSame('especial', (new MaintenanceReport($this->manutencao(notes: 'trocamos o plugin')))->text());
        $this->assertSame('geral', (new MaintenanceReport($this->manutencao()))->text());
    }

    /** Modelo desativado não sai, mesmo sendo o único que serve. */
    public function test_an_inactive_template_is_skipped(): void
    {
        $this->modelo(['variations' => ['nao devo sair'], 'active' => false]);

        $this->assertStringContainsString('Relatório de Manutenção', (new MaintenanceReport($this->manutencao()))->text());
    }

    /** Nenhuma regra batendo cai no texto padrão, e não numa mensagem em branco. */
    public function test_when_no_rule_matches_the_built_in_text_comes_back(): void
    {
        $this->modelo([
            'variations' => ['so para a Padoca'],
            'conditions' => [['field' => 'cliente', 'operator' => 'igual', 'value' => 'Outra Empresa']],
        ]);

        $this->assertStringContainsString('Relatório de Manutenção', (new MaintenanceReport($this->manutencao()))->text());
    }

    public function test_a_variation_is_drawn_from_the_list(): void
    {
        $this->modelo(['variations' => ['um', 'dois', 'tres']]);

        $sorteados = [];

        for ($i = 0; $i < 40; $i++) {
            $sorteados[] = (new MaintenanceReport($this->manutencao()))->text();
        }

        $this->assertSame(['dois', 'tres', 'um'], collect($sorteados)->unique()->sort()->values()->all());
    }

    // ── As regras ────────────────────────────────────────────────────────────

    public function test_the_operators_compare_the_way_people_expect(): void
    {
        $fatos = ['itens_feitos' => 5, 'cliente' => 'Padaria Pão Quente', 'tem_observacoes' => false];

        $passa = fn (array $condicao) => MessageRules::passes([$condicao], $fatos);

        $this->assertTrue($passa(['field' => 'itens_feitos', 'operator' => 'maior', 'value' => '3']));
        $this->assertFalse($passa(['field' => 'itens_feitos', 'operator' => 'maior', 'value' => '5']));
        $this->assertTrue($passa(['field' => 'itens_feitos', 'operator' => 'igual', 'value' => '5']));
        $this->assertTrue($passa(['field' => 'cliente', 'operator' => 'contem', 'value' => 'padaria']));
        $this->assertTrue($passa(['field' => 'tem_observacoes', 'operator' => 'igual', 'value' => 'não']));
        $this->assertTrue($passa(['field' => 'tem_observacoes', 'operator' => 'vazio']));
    }

    /** Acento e caixa não podem decidir se a mensagem sai. */
    public function test_text_comparison_ignores_case_and_accents(): void
    {
        $this->assertTrue(MessageRules::passes(
            [['field' => 'cliente', 'operator' => 'igual', 'value' => 'padaria pao quente']],
            ['cliente' => 'Padaria Pão Quente']
        ));
    }

    /** Todas as condições precisam passar — nunca "ou". */
    public function test_every_condition_has_to_pass(): void
    {
        $condicoes = [
            ['field' => 'itens_feitos', 'operator' => 'maior', 'value' => '1'],
            ['field' => 'tem_observacoes', 'operator' => 'igual', 'value' => 'sim'],
        ];

        $this->assertFalse(MessageRules::passes($condicoes, ['itens_feitos' => 5, 'tem_observacoes' => false]));
        $this->assertTrue(MessageRules::passes($condicoes, ['itens_feitos' => 5, 'tem_observacoes' => true]));
    }

    /** Operador que não existe barra: uma regra escrita não pode virar decoração. */
    public function test_an_unknown_operator_blocks(): void
    {
        $this->assertFalse(MessageRules::passes([['field' => 'x', 'operator' => 'quase', 'value' => '1']], ['x' => 1]));
    }

    // ── O texto ──────────────────────────────────────────────────────────────

    public function test_an_optional_block_disappears_when_empty(): void
    {
        $corpo = 'Pronto.[[

_{{manutencao.observacoes}}_]]';

        $this->assertSame('Pronto.', MessageComposer::render($corpo, ['manutencao.observacoes' => '']));
        $this->assertSame("Pronto.\n\n_trocamos o plugin_", MessageComposer::render($corpo, ['manutencao.observacoes' => 'trocamos o plugin']));
    }

    /** Marcador sem valor não deixa buraco na mensagem. */
    public function test_empty_variables_do_not_leave_holes(): void
    {
        $texto = MessageComposer::render("Olá!\n{{vazio}}\n\n\nFim.", ['vazio' => '']);

        $this->assertSame("Olá!\n\nFim.", $texto);
    }

    public function test_the_built_in_text_greets_without_a_stray_comma(): void
    {
        $client = Client::factory()->create(['contact_name' => null, 'phone' => '11988887777']);
        $plan = MaintenancePlan::factory()->create(['client_id' => $client->id]);
        $maintenance = Maintenance::create([
            'maintenance_plan_id' => $plan->id,
            'user_id' => User::factory()->create()->id,
            'performed_at' => Carbon::parse('2026-08-17'),
            'items' => [],
        ]);

        $this->assertStringContainsString('Olá!', (new MaintenanceReport($maintenance))->text());
    }

    // ── A tela ───────────────────────────────────────────────────────────────

    public function test_the_page_lists_the_templates_and_the_catalog(): void
    {
        $this->modelo(['name' => 'Meu texto']);

        $this->get(self::URL)->assertOk()->assertInertia(
            fn ($page) => $page->component('configuracoes/mensagens')
                ->where('templates.0.name', 'Meu texto')
                // A chave do gatilho tem ponto, que o assert leria como caminho.
                ->where('triggers', fn ($triggers) => collect($triggers)->keys()->contains(MessageTriggers::MAINTENANCE_DONE))
                ->has('starters')
        );
    }

    /** A lista mostra a regra em português, e não a estrutura crua. */
    public function test_the_rules_come_written_out(): void
    {
        $this->modelo(['conditions' => [['field' => 'itens_feitos', 'operator' => 'maior', 'value' => '3']]]);

        $this->get(self::URL)->assertOk()->assertInertia(
            fn ($page) => $page->where('templates.0.rules.0', 'Itens executados é maior que 3')
        );
    }

    public function test_a_template_can_be_created(): void
    {
        $this->post(self::URL, [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Novo',
            'variations' => ['Olá, {{cliente.contato}}!', 'Oi, {{cliente.primeiro_nome}}!'],
            'conditions' => [['field' => 'itens_feitos', 'operator' => 'maior', 'value' => '0']],
            'priority' => 5,
            'active' => true,
        ])->assertRedirect();

        $modelo = MessageTemplate::firstWhere('name', 'Novo');

        $this->assertCount(2, $modelo->variations);
        $this->assertSame(5, $modelo->priority);
    }

    /** Marcador que o gatilho não conhece chegaria em branco no WhatsApp do cliente. */
    public function test_an_unknown_marker_is_refused(): void
    {
        $this->post(self::URL, [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Errado',
            'variations' => ['Olá, {{cliente.aniversario}}!'],
        ])->assertSessionHasErrors('variations.0');

        $this->assertSame(0, MessageTemplate::count());
    }

    public function test_a_condition_on_an_unknown_field_is_refused(): void
    {
        $this->post(self::URL, [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Errado',
            'variations' => ['Olá!'],
            'conditions' => [['field' => 'signo_do_cliente', 'operator' => 'igual', 'value' => 'áries']],
        ])->assertSessionHasErrors('conditions.0.field');
    }

    public function test_a_template_needs_at_least_one_variation(): void
    {
        $this->post(self::URL, [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Vazio',
            'variations' => [],
        ])->assertSessionHasErrors('variations');
    }

    public function test_a_template_can_be_edited_and_removed(): void
    {
        $modelo = $this->modelo();

        $this->put(self::URL.'/'.$modelo->id, [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Renomeado',
            'variations' => ['Outro texto'],
            'active' => false,
        ])->assertRedirect();

        $this->assertSame('Renomeado', $modelo->fresh()->name);
        $this->assertFalse($modelo->fresh()->active);

        $this->delete(self::URL.'/'.$modelo->id)->assertRedirect();
        $this->assertSame(0, MessageTemplate::count());
    }

    /** A prévia usa a mesma conta do envio, inclusive os blocos opcionais. */
    public function test_the_preview_renders_with_example_data(): void
    {
        $resposta = $this->getJson(self::URL.'/previa?'.http_build_query([
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'body' => 'Olá, {{cliente.contato}}! [[Obs: {{nada}}]]',
        ]))->assertOk();

        $this->assertSame('Olá, Maria Souza!', $resposta->json('text'));
        $this->assertSame(['nada'], $resposta->json('unknown'));
    }

    public function test_someone_without_settings_does_not_get_in(): void
    {
        $this->actingAs(User::factory()->member()->create(['permissions' => ['configuracoes' => 'none']]))
            ->get(self::URL)
            ->assertForbidden();
    }
}
