<?php

namespace Tests\Feature\Settings;

use App\Models\Client;
use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\MailSetting;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Support\MaintenanceReport;
use App\Support\MessageDelivery;
use App\Support\MessageTriggers;
use App\Support\Smtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * O servidor de e-mail e o canal de e-mail dos modelos.
 *
 * A senha do correio abre o direito de mandar e-mail em nome da agência: ela
 * nunca pode voltar para a tela nem ficar legível no banco.
 */
class MailSettingTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/configuracoes/email';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['email' => 'chefe@agenciamay.com.br']);
        $this->actingAs($this->admin);
    }

    /**
     * Um transporte de mentira no lugar do servidor de verdade.
     *
     * `Mail::fake()` não serve aqui: ele ignora `Mail::raw`, que é justamente
     * como o sistema manda estas mensagens — o teste passaria sem nada ter
     * sido enviado.
     */
    private function correio(): ArrayTransport
    {
        $transporte = new ArrayTransport;

        foreach (['smtp', 'array'] as $driver) {
            Mail::extend($driver, fn () => $transporte);
        }

        return $transporte;
    }

    /**
     * @return list<array{to: string, subject: string, body: string}>
     */
    private function enviados(ArrayTransport $transporte): array
    {
        return $transporte->messages()->map(fn ($enviada) => [
            'to' => $enviada->getOriginalMessage()->getTo()[0]->getAddress(),
            'subject' => $enviada->getOriginalMessage()->getSubject(),
            'body' => $enviada->getOriginalMessage()->getTextBody(),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function dados(array $extra = []): array
    {
        return $extra + [
            'host' => 'smtp.agenciamay.com.br',
            'port' => 587,
            'username' => 'contato@agenciamay.com.br',
            'password' => 'senha-secreta',
            'encryption' => 'tls',
            'from_address' => 'contato@agenciamay.com.br',
            'from_name' => 'Agência May',
            'active' => true,
        ];
    }

    // ── A configuração ───────────────────────────────────────────────────────

    public function test_the_settings_can_be_saved(): void
    {
        $this->put(self::URL, $this->dados())->assertRedirect();

        $settings = MailSetting::sole();

        $this->assertSame('smtp.agenciamay.com.br', $settings->host);
        $this->assertSame(587, $settings->port);
        $this->assertTrue($settings->isUsable());
    }

    /** A senha fica cifrada em repouso: quem lê o banco não lê a senha. */
    public function test_the_password_is_not_readable_in_the_database(): void
    {
        $this->put(self::URL, $this->dados())->assertRedirect();

        $cru = DB::table('mail_settings')->value('password');

        $this->assertNotSame('senha-secreta', $cru);
        $this->assertSame('senha-secreta', MailSetting::sole()->password);
    }

    /** A senha nunca volta para a tela — só se sabe que existe. */
    public function test_the_screen_never_receives_the_password(): void
    {
        $this->put(self::URL, $this->dados());

        $this->get(self::URL)->assertOk()->assertInertia(
            fn ($page) => $page->component('configuracoes/email')
                ->where('settings.has_password', true)
                ->missing('settings.password')
        );
    }

    /** Campo de senha em branco significa "não mexi", e não "apague". */
    public function test_an_empty_password_field_keeps_the_saved_one(): void
    {
        $this->put(self::URL, $this->dados());
        $this->put(self::URL, $this->dados(['password' => '', 'host' => 'outro.servidor.com.br']));

        $settings = MailSetting::sole();

        $this->assertSame('outro.servidor.com.br', $settings->host);
        $this->assertSame('senha-secreta', $settings->password);
    }

    public function test_the_settings_demand_the_essentials(): void
    {
        $this->put(self::URL, ['port' => 587])->assertSessionHasErrors(['host', 'from_address', 'from_name']);
    }

    public function test_only_an_admin_gets_in(): void
    {
        $this->actingAs(User::factory()->member()->create())->get(self::URL)->assertForbidden();
    }

    // ── O envio de teste ─────────────────────────────────────────────────────

    /** O teste vai para quem pediu, e o resultado fica gravado. */
    public function test_the_test_email_goes_to_whoever_asked(): void
    {
        $correio = $this->correio();
        $this->put(self::URL, $this->dados());

        $this->post(self::URL.'/teste')->assertRedirect()->assertSessionHas('success');

        $this->assertSame('chefe@agenciamay.com.br', $this->enviados($correio)[0]['to']);
        $this->assertNotNull(MailSetting::sole()->tested_at);
    }

    /** Testar uma configuração ainda desativada funciona: é para isso que serve. */
    public function test_an_inactive_setting_can_still_be_tested(): void
    {
        $correio = $this->correio();
        $this->put(self::URL, $this->dados(['active' => false]));

        $this->post(self::URL.'/teste')->assertSessionHas('success');

        $this->assertCount(1, $this->enviados($correio));
        $this->assertFalse(MailSetting::sole()->active);
    }

    public function test_testing_before_saving_says_so(): void
    {
        $this->post(self::URL.'/teste')->assertRedirect()->assertSessionHas('error');
    }

    // ── O canal de e-mail nos modelos ────────────────────────────────────────

    private function manutencao(): Maintenance
    {
        $client = Client::factory()->create([
            'name' => 'Padaria Pão Quente',
            'contact_name' => 'Maria Souza',
            'phone' => '11988887777',
            'email' => 'maria@paoquente.com.br',
        ]);
        $plan = MaintenancePlan::factory()->create(['client_id' => $client->id, 'site_url' => 'paoquente.com.br']);

        return Maintenance::create([
            'maintenance_plan_id' => $plan->id,
            'user_id' => $this->admin->id,
            'performed_at' => Carbon::parse('2026-08-17'),
            'items' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function modelo(array $extra = []): MessageTemplate
    {
        return MessageTemplate::create($extra + [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Por e-mail',
            'variations' => ['O site {{site.url}} passou pela manutenção.'],
            'channels' => [MessageDelivery::EMAIL],
            'recipients' => [MessageDelivery::CLIENT],
            'subject' => 'Manutenção de {{site.url}}',
            'active' => true,
        ]);
    }

    public function test_a_template_can_go_out_by_email(): void
    {
        $correio = $this->correio();
        $this->modelo();

        $resultado = (new MaintenanceReport($this->manutencao()))->send();

        $this->assertTrue($resultado['ok']);
        $this->assertStringContainsString('E-mail', $resultado['message']);

        $enviado = $this->enviados($correio)[0];

        $this->assertSame('maria@paoquente.com.br', $enviado['to']);
        $this->assertSame('Manutenção de paoquente.com.br', $enviado['subject']);
        $this->assertStringContainsString('paoquente.com.br passou pela manutenção', $enviado['body']);
    }

    /** O assunto aceita marcadores, como o texto. */
    public function test_the_subject_takes_markers_too(): void
    {
        $this->post('/configuracoes/mensagens', [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Errado',
            'variations' => ['Olá!'],
            'channels' => [MessageDelivery::EMAIL],
            'recipients' => [MessageDelivery::CLIENT],
            'subject' => 'Sobre {{cliente.signo}}',
        ])->assertSessionHasErrors('subject');
    }

    /** Marcar e-mail sem assunto não salva: e-mail sem assunto vai para spam. */
    public function test_the_email_channel_demands_a_subject(): void
    {
        $this->post('/configuracoes/mensagens', [
            'trigger' => MessageTriggers::MAINTENANCE_DONE,
            'name' => 'Sem assunto',
            'variations' => ['Olá!'],
            'channels' => [MessageDelivery::EMAIL],
            'recipients' => [MessageDelivery::CLIENT],
        ])->assertSessionHasErrors('subject');
    }

    /** Um modelo pode sair pelos dois canais de uma vez. */
    public function test_both_channels_at_once(): void
    {
        $correio = $this->correio();
        $this->modelo(['channels' => [MessageDelivery::EMAIL, MessageDelivery::WHATSAPP]]);

        $resultado = (new MaintenanceReport($this->manutencao()))->send();

        // O WhatsApp não está conectado neste teste, então o aviso saiu pela metade.
        $this->assertFalse($resultado['ok']);
        $this->assertSame(['E-mail'], $resultado['sent']);
        $this->assertStringContainsString('WhatsApp não está conectado', $resultado['message']);
        $this->assertCount(1, $this->enviados($correio));
    }

    /** Quem não tem o contato do canal é pulado, e a tela diz qual faltou. */
    public function test_someone_without_the_contact_is_skipped_with_a_reason(): void
    {
        $correio = $this->correio();
        $this->modelo(['recipients' => [MessageDelivery::CLIENT]]);

        $manutencao = $this->manutencao();
        $manutencao->plan->client->update(['email' => null]);

        $resultado = (new MaintenanceReport($manutencao->fresh()))->send();

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('não tem e-mail cadastrado', $resultado['message']);
        $this->assertSame([], $this->enviados($correio));
    }

    /** Administrador recebe por e-mail sem precisar de telefone cadastrado. */
    public function test_the_admins_can_be_the_recipients(): void
    {
        $correio = $this->correio();
        $this->modelo(['recipients' => [MessageDelivery::ADMINS]]);

        (new MaintenanceReport($this->manutencao()))->send();

        $this->assertSame('chefe@agenciamay.com.br', $this->enviados($correio)[0]['to']);
    }

    /** A mesma pessoa em dois grupos recebe uma vez, e não duas. */
    public function test_nobody_gets_the_same_message_twice(): void
    {
        $correio = $this->correio();
        $this->modelo(['recipients' => [MessageDelivery::ADMINS, MessageDelivery::ASSIGNED]]);

        // Quem executou a manutenção é o próprio administrador.
        (new MaintenanceReport($this->manutencao()))->send();

        $this->assertCount(1, $this->enviados($correio));
    }

    // ── A ligação com o Laravel ──────────────────────────────────────────────

    /** A configuração salva sobrescreve a do .env. */
    public function test_the_saved_server_overrides_the_env(): void
    {
        $this->put(self::URL, $this->dados(['host' => 'smtp.provedor.com.br']));

        $this->assertTrue(Smtp::apply());
        $this->assertSame('smtp.provedor.com.br', Config::get('mail.mailers.smtp.host'));
        $this->assertSame('contato@agenciamay.com.br', Config::get('mail.from.address'));
    }

    /** Desativada, ela não sobrescreve nada — e o sistema não manda e-mail. */
    public function test_an_inactive_setting_is_ignored(): void
    {
        $this->put(self::URL, $this->dados(['active' => false]));

        $this->assertFalse(Smtp::apply());
        $this->assertFalse(Smtp::configured());
    }
}
