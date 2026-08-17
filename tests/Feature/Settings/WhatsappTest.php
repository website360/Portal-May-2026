<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\WhatsappConnection;
use App\Support\Evolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integração com o Evolution Go.
 *
 * Nenhum teste fala com servidor de verdade — o comportamento que interessa é o
 * nosso: usar a chave certa em cada chamada, guardar segredo cifrado, traduzir
 * erro em texto legível, e nunca derrubar a tela quando o servidor externo
 * estiver fora.
 */
class WhatsappTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/configuracoes/whatsapp';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    private function conexao(array $extras = []): WhatsappConnection
    {
        return WhatsappConnection::create([
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => 'chave-secreta',
            ...$extras,
        ]);
    }

    /** Instância já registrada e com token, para os testes que não a criam. */
    private function conexaoPronta(): WhatsappConnection
    {
        return $this->conexao(['instance_token' => 'token-da-instancia', 'instance_id' => 'abc-123']);
    }

    // ── Tela e cadastro ──────────────────────────────────────────────────────

    public function test_only_an_admin_reaches_the_page(): void
    {
        $this->get(self::URL)->assertOk();

        $this->actingAs(User::factory()->member()->create())
            ->get(self::URL)
            ->assertForbidden();
    }

    public function test_the_connection_can_be_saved(): void
    {
        $this->put(self::URL, [
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => 'chave-secreta',
        ])->assertSessionHasNoErrors();

        $this->assertSame('agencia-may', WhatsappConnection::sole()->instance);
    }

    /** A chave abre o servidor inteiro: não pode ficar legível no banco. */
    public function test_the_api_key_is_encrypted_at_rest(): void
    {
        $this->conexao();

        $bruto = \DB::table('whatsapp_connections')->value('api_key');

        $this->assertNotSame('chave-secreta', $bruto);
        $this->assertSame('chave-secreta', WhatsappConnection::sole()->api_key);
    }

    /** O token da instância autoriza enviar mensagem: vale tanto quanto a chave. */
    public function test_the_instance_token_is_encrypted_at_rest(): void
    {
        $this->conexaoPronta();

        $bruto = \DB::table('whatsapp_connections')->value('instance_token');

        $this->assertNotSame('token-da-instancia', $bruto);
        $this->assertSame('token-da-instancia', WhatsappConnection::sole()->instance_token);
    }

    /** E nenhum dos dois volta para a tela — só a informação de que existem. */
    public function test_the_secrets_never_go_back_to_the_screen(): void
    {
        $this->conexaoPronta();

        $this->get(self::URL)->assertInertia(
            fn ($page) => $page->where('connection.has_key', true)
                ->missing('connection.api_key')
                ->missing('connection.instance_token')
        );
    }

    public function test_an_empty_key_on_edit_keeps_the_current_one(): void
    {
        $this->conexao();

        $this->put(self::URL, [
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('chave-secreta', WhatsappConnection::sole()->api_key);
    }

    public function test_the_first_setup_demands_a_key(): void
    {
        $this->put(self::URL, [
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
        ])->assertSessionHasErrors('api_key');

        $this->assertSame(0, WhatsappConnection::count());
    }

    public function test_an_instance_name_with_spaces_is_rejected(): void
    {
        $this->put(self::URL, [
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agência may',
            'api_key' => 'x',
        ])->assertSessionHasErrors('instance');
    }

    /**
     * Trocar o servidor ou a instância invalida o token guardado.
     *
     * Ele pertence a uma instância específica de um servidor específico;
     * mantê-lo faria as chamadas seguintes falharem com 401 sem explicação.
     */
    public function test_changing_the_server_clears_the_instance_token(): void
    {
        $this->conexaoPronta();

        $this->put(self::URL, [
            'base_url' => 'https://outro.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => '',
        ])->assertSessionHasNoErrors();

        $conexao = WhatsappConnection::sole();

        $this->assertNull($conexao->instance_token);
        $this->assertNull($conexao->instance_id);
    }

    public function test_keeping_the_same_server_keeps_the_token(): void
    {
        $this->conexaoPronta();

        $this->put(self::URL, [
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('token-da-instancia', WhatsappConnection::sole()->instance_token);
    }

    // ── Os dois níveis de autenticação ───────────────────────────────────────

    /**
     * A chave global lista e cria; o token da instância faz o resto.
     *
     * Trocar um pelo outro devolve 401 no servidor real — foi o que fez a
     * primeira versão deste cliente falhar inteira, contra a API errada.
     */
    public function test_management_uses_the_global_key_and_the_rest_uses_the_instance_token(): void
    {
        $conexao = $this->conexao();

        Http::fake([
            '*/instance/all' => Http::response(['data' => [
                ['id' => 'abc-123', 'name' => 'agencia-may', 'token' => 'token-da-instancia'],
            ]], 200),
            '*/instance/status' => Http::response(['data' => ['Connected' => true, 'LoggedIn' => true]], 200),
        ]);

        (new Evolution($conexao))->state();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/instance/all') && $r->header('apikey')[0] === 'chave-secreta');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/instance/status') && $r->header('apikey')[0] === 'token-da-instancia');
    }

    /** A instância já existente é adotada, não duplicada. */
    public function test_an_existing_instance_is_adopted(): void
    {
        $conexao = $this->conexao();

        Http::fake([
            '*/instance/all' => Http::response(['data' => [
                ['id' => 'abc-123', 'name' => 'agencia-may', 'token' => 'token-existente'],
            ]], 200),
        ]);

        $this->assertTrue((new Evolution($conexao))->ensureInstance()['ok']);
        $this->assertSame('token-existente', $conexao->refresh()->instance_token);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/instance/create'));
    }

    /** O token é nosso: sem enviá-lo, o servidor responde "token is required". */
    public function test_creating_an_instance_supplies_our_own_token(): void
    {
        $conexao = $this->conexao();

        Http::fake([
            '*/instance/all' => Http::response(['data' => []], 200),
            '*/instance/create' => Http::response(['data' => ['id' => 'novo-id', 'name' => 'agencia-may', 'token' => 'token-novo']], 200),
        ]);

        $this->assertTrue((new Evolution($conexao))->ensureInstance()['ok']);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/instance/create')
            && $r['name'] === 'agencia-may'
            && filled($r['token'] ?? null));

        $conexao->refresh();

        $this->assertSame('token-novo', $conexao->instance_token);
        $this->assertSame('novo-id', $conexao->instance_id);
    }

    // ── QR Code ──────────────────────────────────────────────────────────────

    /** O QR sai de dois passos: abrir a sessão e então buscar o código. */
    public function test_the_qr_takes_two_steps(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake([
            '*/instance/all' => Http::response(['data' => [['id' => 'abc-123', 'name' => 'agencia-may', 'token' => 'token-da-instancia']]], 200),
            '*/instance/connect' => Http::response(['message' => 'success'], 200),
            '*/instance/qr' => Http::response(['data' => ['qrcode' => 'AAAA']], 200),
        ]);

        $resultado = (new Evolution($conexao))->qrCode();

        $this->assertTrue($resultado['ok']);
        $this->assertSame('data:image/png;base64,AAAA', $resultado['qr']);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/instance/connect') && $r->method() === 'POST');
    }

    /** Código que já vem como imagem embutida não é embrulhado de novo. */
    public function test_an_embedded_image_is_used_as_is(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake([
            '*/instance/all' => Http::response(['data' => [['id' => 'a', 'name' => 'agencia-may', 'token' => 'token-da-instancia']]], 200),
            '*/instance/connect' => Http::response([], 200),
            '*/instance/qr' => Http::response(['data' => ['qrcode' => 'data:image/png;base64,BBBB']], 200),
        ]);

        $this->assertSame('data:image/png;base64,BBBB', (new Evolution($conexao))->qrCode()['qr']);
    }

    /**
     * "no QR code available" é espera, não falha.
     *
     * Tratar como erro faria a tela desistir justo quando bastava insistir mais
     * um instante — o servidor leva alguns segundos para abrir a sessão.
     */
    public function test_a_qr_that_is_not_ready_yet_is_not_a_failure(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake([
            '*/instance/all' => Http::response(['data' => [['id' => 'a', 'name' => 'agencia-may', 'token' => 'token-da-instancia']]], 200),
            '*/instance/connect' => Http::response([], 200),
            '*/instance/qr' => Http::response(['error' => 'no QR code available. Please wait a moment and try again'], 400),
        ]);

        $resultado = (new Evolution($conexao))->qrCode();

        $this->assertTrue($resultado['ok']);
        $this->assertNull($resultado['qr']);
        $this->assertSame(Evolution::QR_PENDING, $resultado['state']);
    }

    public function test_asking_for_a_qr_without_configuring_says_so(): void
    {
        $this->getJson(self::URL.'/qrcode')->assertStatus(422)->assertJson(['ok' => false]);
    }

    // ── Estado ───────────────────────────────────────────────────────────────

    /** Conectado sem estar autenticado ainda é "conectando", não "conectado". */
    public function test_the_state_separates_connected_from_logged_in(): void
    {
        $casos = [
            [['Connected' => true, 'LoggedIn' => true], WhatsappConnection::STATUS_CONNECTED],
            [['Connected' => true, 'LoggedIn' => false], WhatsappConnection::STATUS_CONNECTING],
            [['Connected' => false, 'LoggedIn' => false], WhatsappConnection::STATUS_DISCONNECTED],
        ];

        $conexao = $this->conexaoPronta();

        /*
         * Um stub só, lendo uma variável que muda a cada volta: chamar
         * Http::fake() de novo acumula em vez de substituir, e todas as
         * iterações receberiam a resposta da primeira.
         */
        $atual = [];

        // Closure com referência, e não função seta: a seta captura por valor,
        // e o stub responderia sempre com o primeiro estado.
        Http::fake(function () use (&$atual) {
            return Http::response(['data' => $atual], 200);
        });

        foreach ($casos as [$resposta, $esperado]) {
            $atual = $resposta;

            $this->assertSame($esperado, (new Evolution($conexao))->state()['status'], json_encode($resposta));
        }
    }

    /** O JID vem com sufixo; o que interessa é o número. */
    public function test_the_number_comes_out_of_the_jid(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake(['*/instance/status' => Http::response(['data' => [
            'Connected' => true, 'LoggedIn' => true, 'Jid' => '5511999998888:12@s.whatsapp.net',
        ]], 200)]);

        $this->assertSame('5511999998888', (new Evolution($conexao))->state()['number']);
    }

    public function test_the_state_is_recorded_after_asking_the_server(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake(['*/instance/status' => Http::response(['data' => [
            'Connected' => true, 'LoggedIn' => true, 'Jid' => '5511999998888@s.whatsapp.net',
        ]], 200)]);

        $this->getJson(self::URL.'/estado')->assertOk()->assertJson(['status' => WhatsappConnection::STATUS_CONNECTED]);

        $conexao->refresh();

        $this->assertTrue($conexao->isConnected());
        $this->assertSame('5511999998888', $conexao->number);
        $this->assertNotNull($conexao->checked_at);
    }

    /** Servidor fora do ar não pode virar erro 500 na tela. */
    public function test_a_server_that_is_down_answers_gracefully(): void
    {
        $this->conexaoPronta();

        Http::fake(['*' => Http::response('', 500)]);

        $this->getJson(self::URL.'/estado')
            ->assertOk()
            ->assertJson(['ok' => false, 'status' => WhatsappConnection::STATUS_DISCONNECTED]);
    }

    // ── Envio ────────────────────────────────────────────────────────────────

    /**
     * Os telefones do sistema vêm mascarados; o servidor quer só dígitos com o
     * código do país.
     */
    public function test_the_number_is_normalized_before_sending(): void
    {
        $casos = [
            '(11) 98888-7777' => '5511988887777',
            '11988887777' => '5511988887777',
            '(11) 4996-4390' => '551149964390',
            '5511988887777' => '5511988887777',
            '+55 11 98888-7777' => '5511988887777',
        ];

        foreach ($casos as $entrada => $esperado) {
            $this->assertSame($esperado, Evolution::normalizeNumber($entrada), "entrada: {$entrada}");
        }
    }

    public function test_sending_a_message_reports_success(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake(['*/send/text' => Http::response(['message' => 'success'], 200)]);

        $resultado = (new Evolution($conexao))->sendText('(11) 98888-7777', 'Olá');

        $this->assertTrue($resultado['ok']);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/send/text')
            && $r['number'] === '5511988887777'
            && $r['text'] === 'Olá'
            && $r->header('apikey')[0] === 'token-da-instancia');
    }

    public function test_a_failed_send_does_not_throw(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $resultado = (new Evolution($conexao))->sendText('11988887777', 'Olá');

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('erro', $resultado['message']);
    }

    /** A explicação do servidor é mais útil que qualquer texto genérico meu. */
    public function test_the_server_explanation_reaches_the_screen(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake(['*/send/text' => Http::response(['error' => 'number not registered on whatsapp'], 400)]);

        $resultado = (new Evolution($conexao))->sendText('11988887777', 'Olá');

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('number not registered', $resultado['message']);
    }

    public function test_a_refused_key_is_explained_in_plain_portuguese(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake(['*' => Http::response(['error' => 'not authorized'], 401)]);

        $this->assertSame(
            'O servidor recusou a chave de API.',
            (new Evolution($conexao))->sendText('11988887777', 'Olá')['message']
        );
    }

    /** Rota inexistente é endereço errado, não chave errada. */
    public function test_a_missing_route_points_at_the_address(): void
    {
        $conexao = $this->conexaoPronta();

        Http::fake(['*' => Http::response('404 page not found', 404)]);

        $this->assertStringContainsString(
            'endereço',
            (new Evolution($conexao))->sendText('11988887777', 'Olá')['message']
        );
    }

    public function test_disconnecting_clears_the_recorded_state(): void
    {
        $conexao = $this->conexaoPronta();
        $conexao->update(['status' => WhatsappConnection::STATUS_CONNECTED, 'number' => '5511999998888']);

        Http::fake(['*/instance/logout' => Http::response(['message' => 'success'], 200)]);

        $this->delete(self::URL)->assertSessionHasNoErrors();

        $conexao->refresh();

        $this->assertFalse($conexao->isConnected());
        $this->assertNull($conexao->number);
    }

    // ── Falhas de conexão ────────────────────────────────────────────────────

    /**
     * Falha de conexão é exceção, não resposta de erro — e subia até a tela
     * como erro do sistema, culpando o Sistema May por um problema de rede.
     */
    public function test_a_connection_failure_does_not_blow_up_the_screen(): void
    {
        $this->conexaoPronta();

        Http::fake(fn () => throw new ConnectionException(
            'cURL error 60: SSL certificate problem: unable to get local issuer certificate'
        ));

        $resposta = $this->getJson(self::URL.'/qrcode')->assertOk();

        $this->assertFalse($resposta->json('ok'));
        $this->assertStringContainsString('certificado', $resposta->json('message'));
        // A mensagem aponta o caminho, em vez de repetir "cURL error 60".
        $this->assertStringContainsString('php.ini', $resposta->json('message'));
    }

    public function test_each_kind_of_connection_failure_gets_its_own_explanation(): void
    {
        $conexao = $this->conexaoPronta();

        $casos = [
            'Could not resolve host: evolution.exemplo.com.br' => 'não foi encontrado',
            'Connection refused' => 'recusou a conexão',
            'Operation timed out after 15000 milliseconds' => 'não respondeu a tempo',
        ];

        /*
         * Um stub só, lendo uma variável que muda a cada volta: chamar
         * Http::fake() de novo acumula em vez de substituir, e todas as
         * iterações receberiam a exceção da primeira.
         */
        $atual = '';

        Http::fake(function () use (&$atual) {
            throw new ConnectionException($atual);
        });

        foreach ($casos as $bruto => $esperado) {
            $atual = $bruto;

            $resultado = (new Evolution($conexao))->sendText('11988887777', 'Olá');

            $this->assertFalse($resultado['ok']);
            $this->assertStringContainsString($esperado, $resultado['message'], "bruto: {$bruto}");
        }
    }
}
