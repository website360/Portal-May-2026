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
 * Integração com a Evolution API.
 *
 * Nenhum teste fala com servidor de verdade — o comportamento que interessa é o
 * nosso: guardar a chave cifrada, traduzir erro em texto legível, e nunca
 * derrubar a tela quando o servidor externo estiver fora.
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

    private function conexao(): WhatsappConnection
    {
        return WhatsappConnection::create([
            'base_url' => 'https://evolution.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => 'chave-secreta',
        ]);
    }

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

    /** E nunca volta para a tela — só a informação de que existe. */
    public function test_the_key_never_goes_back_to_the_screen(): void
    {
        $this->conexao();

        $this->get(self::URL)->assertInertia(
            fn ($page) => $page->where('connection.has_key', true)->missing('connection.api_key')
        );
    }

    public function test_an_empty_key_on_edit_keeps_the_current_one(): void
    {
        $this->conexao();

        $this->put(self::URL, [
            'base_url' => 'https://outro.exemplo.com.br',
            'instance' => 'agencia-may',
            'api_key' => '',
        ])->assertSessionHasNoErrors();

        $conexao = WhatsappConnection::sole();

        $this->assertSame('https://outro.exemplo.com.br', $conexao->base_url);
        $this->assertSame('chave-secreta', $conexao->api_key);
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

    public function test_the_qr_code_comes_back_for_the_screen(): void
    {
        $this->conexao();

        Http::fake([
            '*/instance/create' => Http::response(['instance' => []], 201),
            '*/instance/connect/*' => Http::response(['base64' => 'data:image/png;base64,AAA'], 200),
        ]);

        $this->getJson(self::URL.'/qrcode')
            ->assertOk()
            ->assertJson(['ok' => true, 'qr' => 'data:image/png;base64,AAA']);
    }

    /** Instância já existente devolve 403 — o que aqui é o estado desejado. */
    public function test_an_existing_instance_is_not_treated_as_failure(): void
    {
        $this->conexao();

        Http::fake([
            '*/instance/create' => Http::response(['message' => 'already in use'], 403),
            '*/instance/connect/*' => Http::response(['qrcode' => ['base64' => 'data:image/png;base64,BBB']], 200),
        ]);

        $this->getJson(self::URL.'/qrcode')->assertOk()->assertJson(['ok' => true, 'qr' => 'data:image/png;base64,BBB']);
    }

    /** Já pareado, não vem QR — e isso não é erro. */
    public function test_no_qr_when_the_device_is_already_paired(): void
    {
        $this->conexao();

        Http::fake([
            '*/instance/create' => Http::response([], 201),
            '*/instance/connect/*' => Http::response(['instance' => ['state' => 'open']], 200),
        ]);

        $this->getJson(self::URL.'/qrcode')->assertOk()->assertJson(['ok' => true, 'qr' => null]);
    }

    public function test_a_refused_key_is_explained_in_plain_portuguese(): void
    {
        $this->conexao();

        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->getJson(self::URL.'/qrcode')
            ->assertOk()
            ->assertJson(['ok' => false, 'message' => 'O servidor recusou a chave de API.']);
    }

    public function test_the_state_is_recorded_after_asking_the_server(): void
    {
        $conexao = $this->conexao();

        Http::fake([
            '*/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open', 'owner' => '5511999998888']], 200),
        ]);

        $this->getJson(self::URL.'/estado')->assertOk()->assertJson(['status' => WhatsappConnection::STATUS_CONNECTED]);

        $conexao->refresh();

        $this->assertTrue($conexao->isConnected());
        $this->assertSame('5511999998888', $conexao->number);
        $this->assertNotNull($conexao->checked_at);
    }

    /** Servidor fora do ar não pode virar erro 500 na tela. */
    public function test_a_server_that_is_down_answers_gracefully(): void
    {
        $this->conexao();

        Http::fake(['*' => Http::response('', 500)]);

        $this->getJson(self::URL.'/estado')
            ->assertOk()
            ->assertJson(['ok' => false, 'status' => WhatsappConnection::STATUS_DISCONNECTED]);
    }

    /**
     * Falha de conexão é exceção, não resposta de erro — e subia até a tela
     * como erro do sistema, culpando o Sistema May por um problema de rede.
     */
    public function test_a_connection_failure_does_not_blow_up_the_screen(): void
    {
        $this->conexao();

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
        $conexao = $this->conexao();

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

    public function test_asking_for_a_qr_without_configuring_says_so(): void
    {
        $this->getJson(self::URL.'/qrcode')->assertStatus(422)->assertJson(['ok' => false]);
    }

    /**
     * Os telefones do sistema vêm mascarados; a Evolution quer só dígitos com
     * o código do país.
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
        $conexao = $this->conexao();

        Http::fake(['*/message/sendText/*' => Http::response(['key' => ['id' => 'ABC']], 201)]);

        $resultado = (new Evolution($conexao))->sendText('(11) 98888-7777', 'Olá');

        $this->assertTrue($resultado['ok']);

        Http::assertSent(fn ($request) => $request['number'] === '5511988887777' && $request['text'] === 'Olá');
    }

    public function test_a_failed_send_does_not_throw(): void
    {
        $conexao = $this->conexao();

        Http::fake(['*' => Http::response(['message' => 'nope'], 500)]);

        $resultado = (new Evolution($conexao))->sendText('11988887777', 'Olá');

        $this->assertFalse($resultado['ok']);
        $this->assertSame('O servidor Evolution respondeu com erro.', $resultado['message']);
    }

    public function test_disconnecting_clears_the_recorded_state(): void
    {
        $conexao = $this->conexao();
        $conexao->update(['status' => WhatsappConnection::STATUS_CONNECTED, 'number' => '5511999998888']);

        Http::fake(['*/instance/logout/*' => Http::response([], 200)]);

        $this->delete(self::URL)->assertSessionHasNoErrors();

        $conexao->refresh();

        $this->assertFalse($conexao->isConnected());
        $this->assertNull($conexao->number);
    }
}
