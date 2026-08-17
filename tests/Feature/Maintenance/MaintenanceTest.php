<?php

namespace Tests\Feature\Maintenance;

use App\Models\Client;
use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WhatsappConnection;
use App\Support\MaintenanceChecklist;
use App\Support\MaintenanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/manutencao';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    private function plano(array $attributes = []): MaintenancePlan
    {
        return MaintenancePlan::factory()->create($attributes);
    }

    public function test_the_page_lists_the_plans(): void
    {
        $plano = $this->plano(['site_url' => 'www.boxlocadora.com.br']);

        $this->get(self::URL)->assertOk()->assertInertia(
            fn ($page) => $page->component('manutencao/index')
                ->where('plans.data.0.site_url', 'www.boxlocadora.com.br')
                ->where('plans.data.0.client.id', $plano->client_id)
        );
    }

    /** A tela monta o formulário com o checklist que o servidor manda. */
    public function test_the_checklist_comes_from_the_server(): void
    {
        $this->get(self::URL)->assertInertia(
            fn ($page) => $page->has('checklist.items', count(MaintenanceChecklist::ITEMS))
                ->has('checklist.results', 3)
                ->where('checklist.items.0.label', 'Atualização Site')
        );
    }

    public function test_a_plan_is_created_without_the_protocol_in_the_address(): void
    {
        $cliente = Client::factory()->create();

        $this->post(self::URL.'/planos', [
            'client_id' => $cliente->id,
            'site_url' => 'https://www.boxlocadora.com.br/',
            'active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('www.boxlocadora.com.br', MaintenancePlan::sole()->site_url);
    }

    public function test_the_same_site_is_not_registered_twice_for_a_client(): void
    {
        $plano = $this->plano();

        $this->post(self::URL.'/planos', [
            'client_id' => $plano->client_id,
            'site_url' => $plano->site_url,
        ])->assertSessionHasErrors('site_url');

        $this->assertSame(1, MaintenancePlan::count());
    }

    /**
     * Um plano cadastrado hoje deve o mês corrente — e só ele. Não é atraso:
     * ainda dá tempo, o dia dentro do mês é livre.
     */
    public function test_a_brand_new_plan_owes_only_the_current_month(): void
    {
        $plano = $this->plano();

        $this->assertSame(1, $plano->pendingMonths());
        $this->assertSame(MaintenancePlan::STATUS_PENDING, $plano->status());
    }

    /** Feita neste mês, em qualquer dia dele, o plano está em dia. */
    public function test_any_day_of_the_month_settles_the_month(): void
    {
        $plano = $this->plano();

        Maintenance::factory()->create([
            'maintenance_plan_id' => $plano->id,
            'performed_at' => Carbon::today()->startOfMonth()->toDateString(),
        ]);

        $this->assertSame(0, $plano->refresh()->pendingMonths());
        $this->assertSame(MaintenancePlan::STATUS_DONE, $plano->status());
    }

    /** Atendido no mês passado: deve só o corrente, e ainda não é atraso. */
    public function test_serviced_last_month_is_pending_not_late(): void
    {
        $plano = $this->plano();

        Maintenance::factory()->create([
            'maintenance_plan_id' => $plano->id,
            'performed_at' => Carbon::today()->startOfMonth()->subMonthNoOverflow()->addDays(3)->toDateString(),
        ]);

        $this->assertSame(1, $plano->refresh()->pendingMonths());
        $this->assertSame(MaintenancePlan::STATUS_PENDING, $plano->status());
    }

    /** Um mês inteiro em branco é atraso, e o tamanho dele aparece. */
    public function test_a_month_gone_by_is_late(): void
    {
        $plano = $this->plano();

        Maintenance::factory()->create([
            'maintenance_plan_id' => $plano->id,
            'performed_at' => Carbon::today()->startOfMonth()->subMonthsNoOverflow(3)->toDateString(),
        ]);

        $plano->refresh();

        $this->assertSame(3, $plano->pendingMonths());
        $this->assertSame(MaintenancePlan::STATUS_LATE, $plano->status());
    }

    /** Cadastrado meses atrás e nunca atendido também é atraso. */
    public function test_a_plan_never_serviced_falls_behind(): void
    {
        $plano = MaintenancePlan::factory()->createdMonthsAgo(2)->create();

        $this->assertSame(3, $plano->pendingMonths());
        $this->assertSame(MaintenancePlan::STATUS_LATE, $plano->status());
    }

    public function test_registering_a_maintenance_settles_the_month(): void
    {
        $plano = MaintenancePlan::factory()->createdMonthsAgo(2)->create();

        $this->post(self::URL."/planos/{$plano->id}/registros", [
            'performed_at' => Carbon::today()->toDateString(),
            'items' => ['backup' => MaintenanceChecklist::SKIPPED],
            'notes' => 'Tudo certo.',
        ])->assertSessionHasNoErrors();

        $plano->refresh();

        $this->assertSame(Carbon::today()->toDateString(), $plano->last_performed_at->toDateString());
        $this->assertSame(MaintenancePlan::STATUS_DONE, $plano->status());
    }

    /** Apagar do histórico devolve a situação para a manutenção anterior. */
    public function test_deleting_a_maintenance_recalculates_the_situation(): void
    {
        $plano = $this->plano();

        Maintenance::factory()->create([
            'maintenance_plan_id' => $plano->id,
            'performed_at' => Carbon::today()->startOfMonth()->subMonthNoOverflow()->toDateString(),
        ]);
        $ultima = Maintenance::factory()->create([
            'maintenance_plan_id' => $plano->id,
            'performed_at' => Carbon::today()->toDateString(),
        ]);

        $this->assertSame(MaintenancePlan::STATUS_DONE, $plano->refresh()->status());

        $this->delete(self::URL."/registros/{$ultima->id}")->assertSessionHasNoErrors();

        $this->assertSame(MaintenancePlan::STATUS_PENDING, $plano->refresh()->status());
    }

    public function test_the_checklist_keeps_the_labels_of_the_day(): void
    {
        $plano = $this->plano();

        $this->post(self::URL."/planos/{$plano->id}/registros", [
            'performed_at' => Carbon::today()->toDateString(),
            'items' => ['backup' => MaintenanceChecklist::NOT_NEEDED],
        ])->assertSessionHasNoErrors();

        $itens = collect(Maintenance::sole()->items);

        $this->assertCount(count(MaintenanceChecklist::ITEMS), $itens);
        $this->assertSame('Backup', $itens->firstWhere('key', 'backup')['label']);
        $this->assertSame(MaintenanceChecklist::NOT_NEEDED, $itens->firstWhere('key', 'backup')['result']);
        // Item não informado é "realizado": a tela abre tudo marcado.
        $this->assertSame(MaintenanceChecklist::DONE, $itens->firstWhere('key', 'site')['result']);
    }

    public function test_a_maintenance_cannot_be_dated_in_the_future(): void
    {
        $plano = $this->plano();

        $this->post(self::URL."/planos/{$plano->id}/registros", [
            'performed_at' => Carbon::tomorrow()->toDateString(),
        ])->assertSessionHasErrors('performed_at');

        $this->assertSame(0, Maintenance::count());
    }

    /**
     * O filtro em SQL tem de concordar com o `status()` em PHP — são dois
     * códigos dizendo a mesma coisa, e é aí que costumam divergir.
     */
    public function test_the_filter_takes_more_than_one_situation(): void
    {
        $atrasado = MaintenancePlan::factory()->createdMonthsAgo(3)->create();

        $pendente = $this->plano();

        $feito = $this->plano();
        Maintenance::factory()->create(['maintenance_plan_id' => $feito->id, 'performed_at' => Carbon::today()->toDateString()]);

        $pausado = MaintenancePlan::factory()->inactive()->create();

        // Cada um cai onde o status() em PHP diz que cai.
        $this->assertSame(MaintenancePlan::STATUS_LATE, $atrasado->status());
        $this->assertSame(MaintenancePlan::STATUS_PENDING, $pendente->status());
        $this->assertSame(MaintenancePlan::STATUS_DONE, $feito->refresh()->status());
        $this->assertSame(MaintenancePlan::STATUS_PAUSED, $pausado->status());

        $this->get(self::URL.'?statuses[]=late&statuses[]=paused')->assertInertia(
            function ($page) use ($atrasado, $pausado, $pendente, $feito) {
                $ids = collect($page->toArray()['props']['plans']['data'])->pluck('id');

                $this->assertTrue($ids->contains($atrasado->id));
                $this->assertTrue($ids->contains($pausado->id));
                $this->assertFalse($ids->contains($pendente->id));
                $this->assertFalse($ids->contains($feito->id));
            }
        );

        $this->get(self::URL.'?statuses[]=pending')->assertInertia(function ($page) use ($pendente, $atrasado) {
            $ids = collect($page->toArray()['props']['plans']['data'])->pluck('id');

            $this->assertTrue($ids->contains($pendente->id));
            $this->assertFalse($ids->contains($atrasado->id));
        });

        $this->get(self::URL.'?statuses[]=done')->assertInertia(function ($page) use ($feito, $pendente) {
            $ids = collect($page->toArray()['props']['plans']['data'])->pluck('id');

            $this->assertTrue($ids->contains($feito->id));
            $this->assertFalse($ids->contains($pendente->id));
        });
    }

    /** @return Collection<int, int> */
    private function idsDe(string $url, string $lista): Collection
    {
        $resposta = $this->get($url);
        $resposta->assertOk();

        return collect($resposta->viewData('page')['props'][$lista]['data'])->pluck('id');
    }

    public function test_the_plans_can_be_filtered_by_client(): void
    {
        $alvo = $this->plano();
        $outro = $this->plano();

        $ids = $this->idsDe(self::URL."?clients[]={$alvo->client_id}", 'plans');

        $this->assertTrue($ids->contains($alvo->id));
        $this->assertFalse($ids->contains($outro->id));
    }

    /** Vários clientes de uma vez — é a carteira de uma pessoa, não um cliente só. */
    public function test_more_than_one_client_at_a_time(): void
    {
        $um = $this->plano();
        $dois = $this->plano();
        $fora = $this->plano();

        $ids = $this->idsDe(self::URL."?clients[]={$um->client_id}&clients[]={$dois->client_id}", 'plans');

        $this->assertTrue($ids->contains($um->id));
        $this->assertTrue($ids->contains($dois->id));
        $this->assertFalse($ids->contains($fora->id));
    }

    public function test_the_history_can_be_filtered_by_client(): void
    {
        $alvo = Maintenance::factory()->create(['maintenance_plan_id' => $this->plano()->id]);
        $outro = Maintenance::factory()->create(['maintenance_plan_id' => $this->plano()->id]);

        $cliente = $alvo->plan->client_id;
        $ids = $this->idsDe(self::URL."?tab=historico&clients[]={$cliente}", 'history');

        $this->assertTrue($ids->contains($alvo->id));
        $this->assertFalse($ids->contains($outro->id));
    }

    public function test_the_history_can_be_filtered_by_month(): void
    {
        $plano = $this->plano();

        $julho = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'performed_at' => '2026-07-10']);
        // Último dia do mês: a comparação como texto costuma perder este.
        $fimDeJulho = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'performed_at' => '2026-07-31']);
        $agosto = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'performed_at' => '2026-08-02']);

        $ids = $this->idsDe(self::URL.'?tab=historico&month=2026-07', 'history');

        $this->assertTrue($ids->contains($julho->id));
        $this->assertTrue($ids->contains($fimDeJulho->id));
        $this->assertFalse($ids->contains($agosto->id));
    }

    /** Mês inválido não pode virar erro nem lista vazia por acidente. */
    public function test_a_broken_month_is_ignored(): void
    {
        Maintenance::factory()->create(['maintenance_plan_id' => $this->plano()->id]);

        $this->assertCount(1, $this->idsDe(self::URL.'?tab=historico&month=sei-la', 'history'));
    }

    /** É deste recorte que sai a lista do que ainda precisa ser reenviado. */
    public function test_the_history_can_show_only_the_reports_that_did_not_go_out(): void
    {
        $plano = $this->plano();

        $enviado = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'notified_at' => now()]);
        $falhou = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'notify_error' => 'sem telefone']);

        $naoEnviados = $this->idsDe(self::URL.'?tab=historico&reports[]=not_sent', 'history');

        $this->assertTrue($naoEnviados->contains($falhou->id));
        $this->assertFalse($naoEnviados->contains($enviado->id));

        $enviados = $this->idsDe(self::URL.'?tab=historico&reports[]=sent', 'history');

        $this->assertTrue($enviados->contains($enviado->id));
        $this->assertFalse($enviados->contains($falhou->id));

        // Os dois marcados não escondem nada — seria o mesmo que nenhum.
        $this->assertCount(2, $this->idsDe(self::URL.'?tab=historico&reports[]=sent&reports[]=not_sent', 'history'));
    }

    public function test_the_history_can_be_filtered_by_who_did_it(): void
    {
        $plano = $this->plano();
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);

        $dela = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'user_id' => $ana->id]);
        $dele = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'user_id' => $bruno->id]);

        $ids = $this->idsDe(self::URL."?tab=historico&users[]={$ana->id}", 'history');

        $this->assertTrue($ids->contains($dela->id));
        $this->assertFalse($ids->contains($dele->id));
    }

    /**
     * As opções dos filtros saem do que existe: oferecer uma pessoa ou um mês
     * sem manutenção nenhuma é oferecer um recorte que volta vazio.
     */
    public function test_the_filter_options_come_from_what_exists(): void
    {
        $plano = $this->plano();
        $ana = User::factory()->create(['name' => 'Ana']);
        User::factory()->create(['name' => 'Nunca Registrou']);

        Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'user_id' => $ana->id, 'performed_at' => '2026-07-10']);
        Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'user_id' => $ana->id, 'performed_at' => '2026-07-20']);

        $this->get(self::URL.'?tab=historico')->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            $this->assertSame(['Ana'], collect($props['executors'])->pluck('label')->all());
            // Julho aparece uma vez só, mesmo com duas manutenções nele.
            $this->assertSame(['2026-07'], $props['months']);
        });
    }

    /** Filtros combinados estreitam, não se anulam. */
    public function test_filters_stack(): void
    {
        $plano = $this->plano();
        $ana = User::factory()->create(['name' => 'Ana']);

        $alvo = Maintenance::factory()->create([
            'maintenance_plan_id' => $plano->id,
            'user_id' => $ana->id,
            'performed_at' => '2026-07-10',
            'notified_at' => null,
        ]);

        // Mesma pessoa, mês errado.
        Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'user_id' => $ana->id, 'performed_at' => '2026-08-10']);

        // Mês certo, mas relatório enviado.
        Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'user_id' => $ana->id, 'performed_at' => '2026-07-15', 'notified_at' => now()]);

        $ids = $this->idsDe(self::URL."?tab=historico&users[]={$ana->id}&month=2026-07&reports[]=not_sent", 'history');

        $this->assertSame([$alvo->id], $ids->all());
    }

    public function test_the_history_tab_lists_what_was_done(): void
    {
        $plano = $this->plano(['site_url' => 'www.boxlocadora.com.br']);

        Maintenance::factory()->create(['maintenance_plan_id' => $plano->id, 'performed_at' => '2026-07-10']);

        $this->get(self::URL.'?tab=historico')->assertInertia(
            fn ($page) => $page->where('history.data.0.site_url', 'www.boxlocadora.com.br')
                ->where('history.data.0.performed_label', '10/07/2026')
                ->where('history.data.0.done_count', count(MaintenanceChecklist::ITEMS))
                // A outra aba não vem junto: são duas consultas, e só uma é pedida.
                ->where('plans', null)
        );
    }

    public function test_deleting_a_plan_takes_its_history(): void
    {
        $plano = $this->plano();

        Maintenance::factory()->create(['maintenance_plan_id' => $plano->id]);

        $this->delete(self::URL."/planos/{$plano->id}")->assertSessionHasNoErrors();

        $this->assertSame(0, Maintenance::count());
    }

    /** O relatório é o que o cliente lê: precisa dizer o que foi feito. */
    public function test_the_report_lists_what_was_done_and_hides_what_was_skipped(): void
    {
        $cliente = Client::factory()->create(['contact_name' => 'Ana', 'phone' => '(11) 98888-7777']);
        $plano = $this->plano(['client_id' => $cliente->id, 'site_url' => 'www.boxlocadora.com.br']);

        $manutencao = Maintenance::factory()->create([
            'maintenance_plan_id' => $plano->id,
            'performed_at' => '2026-08-10',
            'items' => MaintenanceChecklist::from([
                'backup' => MaintenanceChecklist::NOT_NEEDED,
                'seguranca' => MaintenanceChecklist::SKIPPED,
            ]),
            'notes' => 'Trocamos o plugin de cache.',
        ]);

        $texto = (new MaintenanceReport($manutencao))->text();

        $this->assertStringContainsString('Olá, Ana!', $texto);
        $this->assertStringContainsString('www.boxlocadora.com.br', $texto);
        $this->assertStringContainsString('10/08/2026', $texto);
        $this->assertStringContainsString('✅ Atualização Site', $texto);
        $this->assertStringContainsString('➖ Backup (não era necessário)', $texto);
        $this->assertStringContainsString('Trocamos o plugin de cache.', $texto);
        // Pulado é pendência interna: o cliente não tem o que fazer com isso.
        $this->assertStringNotContainsString('Melhoria na segurança', $texto);
    }

    public function test_the_report_goes_out_on_whatsapp(): void
    {
        WhatsappConnection::create([
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => 'chave',
            'instance_token' => 'token-da-instancia',
            'status' => WhatsappConnection::STATUS_CONNECTED,
        ]);

        Http::fake(['*/send/text' => Http::response(['key' => ['id' => 'A']], 201)]);

        $cliente = Client::factory()->create(['phone' => '(11) 98888-7777']);
        $plano = $this->plano(['client_id' => $cliente->id]);

        $this->post(self::URL."/planos/{$plano->id}/registros", [
            'performed_at' => Carbon::today()->toDateString(),
            'notify' => true,
        ])->assertSessionHas('success');

        $this->assertNotNull(Maintenance::sole()->notified_at);
        Http::assertSent(fn ($request) => $request['number'] === '5511988887777');
    }

    /**
     * O envio falhar não pode desfazer o registro: a manutenção aconteceu de
     * verdade, e refazê-la para reenviar o texto seria mentira no histórico.
     */
    public function test_a_failed_report_does_not_undo_the_maintenance(): void
    {
        $cliente = Client::factory()->create(['phone' => '(11) 98888-7777']);
        $plano = $this->plano(['client_id' => $cliente->id]);

        // Sem conexão configurada.
        $this->post(self::URL."/planos/{$plano->id}/registros", [
            'performed_at' => Carbon::today()->toDateString(),
            'notify' => true,
        ])->assertSessionHas('warning');

        $manutencao = Maintenance::sole();

        $this->assertNull($manutencao->notified_at);
        $this->assertStringContainsString('WhatsApp não está conectado', $manutencao->notify_error);
        $this->assertSame(Carbon::today()->toDateString(), $plano->refresh()->last_performed_at->toDateString());
    }

    public function test_a_client_without_a_phone_is_told_so(): void
    {
        WhatsappConnection::create([
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => 'chave',
            'instance_token' => 'token-da-instancia',
            'status' => WhatsappConnection::STATUS_CONNECTED,
        ]);

        $cliente = Client::factory()->create(['phone' => null]);
        $plano = $this->plano(['client_id' => $cliente->id]);

        $manutencao = Maintenance::factory()->create(['maintenance_plan_id' => $plano->id]);

        $resultado = (new MaintenanceReport($manutencao))->send();

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('telefone', $resultado['message']);
    }

    /** Reenviar existe porque a causa da falha se resolve depois. */
    public function test_the_report_can_be_sent_again_later(): void
    {
        WhatsappConnection::create([
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => 'chave',
            'instance_token' => 'token-da-instancia',
            'status' => WhatsappConnection::STATUS_CONNECTED,
        ]);

        Http::fake(['*/send/text' => Http::response([], 201)]);

        $cliente = Client::factory()->create(['phone' => '(11) 98888-7777']);
        $plano = $this->plano(['client_id' => $cliente->id]);
        $manutencao = Maintenance::factory()->create([
            'maintenance_plan_id' => $plano->id,
            'notify_error' => 'WhatsApp não está conectado.',
        ]);

        $this->post(self::URL."/registros/{$manutencao->id}/reenviar")->assertSessionHas('success');

        $manutencao->refresh();

        $this->assertNotNull($manutencao->notified_at);
        $this->assertNull($manutencao->notify_error);
    }

    public function test_someone_without_the_module_does_not_get_in(): void
    {
        $this->actingAs(User::factory()->member()->create(['permissions' => ['manutencao' => 'none']]))
            ->get(self::URL)
            ->assertForbidden();
    }

    public function test_read_only_cannot_register_a_maintenance(): void
    {
        $plano = $this->plano();

        $this->actingAs(User::factory()->member()->create(['permissions' => ['manutencao' => 'read']]))
            ->post(self::URL."/planos/{$plano->id}/registros", ['performed_at' => Carbon::today()->toDateString()])
            ->assertSessionHasErrors('permissao');

        $this->assertSame(0, Maintenance::count());
    }
}
