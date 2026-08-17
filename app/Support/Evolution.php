<?php

namespace App\Support;

use App\Models\WhatsappConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente da Evolution API.
 *
 * Concentra aqui todo o conhecimento sobre o formato da API: os módulos que
 * forem mandar mensagem pedem "envie para este número", e não precisam saber de
 * rota, cabeçalho nem formato de resposta.
 *
 * Nenhum método lança exceção por falha de rede ou resposta de erro — eles
 * devolvem um resultado que diz o que houve. Uma integração externa fora do ar
 * não pode derrubar a tela de quem só queria salvar um cliente.
 */
final class Evolution
{
    public function __construct(private readonly WhatsappConnection $connection) {}

    private function http(): PendingRequest
    {
        return Http::withHeaders(['apikey' => $this->connection->api_key])
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * Executa a chamada devolvendo `null` quando nem chegou a haver resposta.
     *
     * Erro de SSL, DNS ou timeout é exceção no Laravel, não resposta de erro —
     * e uma exceção dessas sobe até a tela como erro do sistema, culpando o
     * Sistema May por um problema que é do servidor ou da rede.
     *
     * @param  \Closure(): Response  $call
     */
    private function attempt(\Closure $call): ?Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            $this->lastError = $this->connectionReason($e->getMessage());

            Log::warning('Evolution: não foi possível conectar', ['erro' => $e->getMessage()]);

            return null;
        }
    }

    private ?string $lastError = null;

    /**
     * Traduz a falha de conexão para algo acionável.
     *
     * "cURL error 60" não diz a ninguém o que fazer; "o PHP não confia no
     * certificado" aponta para o certificado, que é onde está o problema.
     */
    private function connectionReason(string $raw): string
    {
        return match (true) {
            str_contains($raw, 'SSL certificate problem') || str_contains($raw, 'certificate verify failed') => 'O PHP não confia no certificado do servidor. Falta apontar o arquivo de certificados (cacert.pem) no php.ini — veja `curl.cainfo` e `openssl.cafile`.',
            str_contains($raw, 'Could not resolve host') => 'O endereço do servidor não foi encontrado. Confira se o domínio está escrito certo.',
            str_contains($raw, 'Connection refused') => 'O servidor recusou a conexão. Ele está no ar nessa porta?',
            str_contains($raw, 'timed out') || str_contains($raw, 'Timeout') => 'O servidor não respondeu a tempo.',
            default => 'Não foi possível alcançar o servidor.',
        };
    }

    /**
     * Cria a instância caso ainda não exista.
     *
     * A Evolution devolve 403 quando o nome já está em uso — o que aqui não é
     * erro, e sim o estado desejado.
     *
     * @return array{ok: bool, message: string}
     */
    public function ensureInstance(): array
    {
        $response = $this->attempt(fn () => $this->http()->post($this->connection->url('/instance/create'), [
            'instanceName' => $this->connection->instance,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ]));

        if ($response === null) {
            return ['ok' => false, 'message' => $this->lastError];
        }

        if ($response->successful() || $response->status() === 403) {
            return ['ok' => true, 'message' => 'Instância pronta.'];
        }

        return ['ok' => false, 'message' => $this->reason($response->status(), $response->body())];
    }

    /**
     * Pede o QR Code para parear o aparelho.
     *
     * @return array{ok: bool, qr: ?string, message: string}
     */
    public function qrCode(): array
    {
        $created = $this->ensureInstance();

        if (! $created['ok']) {
            return ['ok' => false, 'qr' => null, 'message' => $created['message']];
        }

        $response = $this->attempt(fn () => $this->http()->get($this->connection->url('/instance/connect/'.$this->connection->instance)));

        if ($response === null) {
            return ['ok' => false, 'qr' => null, 'message' => $this->lastError];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'qr' => null, 'message' => $this->reason($response->status(), $response->body())];
        }

        $data = $response->json();

        /*
         * O QR aparece em chaves diferentes conforme a versão: `base64` na
         * resposta direta, `qrcode.base64` quando vem aninhado. Já pareado, não
         * vem nenhuma das duas — e isso não é falha.
         */
        $qr = $data['base64'] ?? $data['qrcode']['base64'] ?? null;

        if ($qr === null) {
            return ['ok' => true, 'qr' => null, 'message' => 'Sem QR Code: o aparelho já parece pareado.'];
        }

        return ['ok' => true, 'qr' => $qr, 'message' => 'Leia o código no WhatsApp do aparelho.'];
    }

    /**
     * Estado atual da conexão, direto do servidor.
     *
     * @return array{ok: bool, status: string, number: ?string, message: string}
     */
    public function state(): array
    {
        $response = $this->attempt(fn () => $this->http()->get($this->connection->url('/instance/connectionState/'.$this->connection->instance)));

        if ($response === null) {
            return ['ok' => false, 'status' => WhatsappConnection::STATUS_DISCONNECTED, 'number' => null, 'message' => $this->lastError];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'status' => WhatsappConnection::STATUS_DISCONNECTED,
                'number' => null,
                'message' => $this->reason($response->status(), $response->body()),
            ];
        }

        $state = $response->json('instance.state') ?? $response->json('state');

        return [
            'ok' => true,
            'status' => match ($state) {
                'open' => WhatsappConnection::STATUS_CONNECTED,
                'connecting' => WhatsappConnection::STATUS_CONNECTING,
                default => WhatsappConnection::STATUS_DISCONNECTED,
            },
            'number' => $response->json('instance.owner') ?? $response->json('instance.profileName'),
            'message' => 'Estado consultado.',
        ];
    }

    /** Desconecta o aparelho sem apagar a instância. */
    public function logout(): array
    {
        $response = $this->attempt(fn () => $this->http()->delete($this->connection->url('/instance/logout/'.$this->connection->instance)));

        if ($response === null) {
            return ['ok' => false, 'message' => $this->lastError];
        }

        return $response->successful()
            ? ['ok' => true, 'message' => 'Aparelho desconectado.']
            : ['ok' => false, 'message' => $this->reason($response->status(), $response->body())];
    }

    /**
     * Envia uma mensagem de texto.
     *
     * @return array{ok: bool, message: string}
     */
    public function sendText(string $number, string $text): array
    {
        $response = $this->attempt(fn () => $this->http()->post($this->connection->url('/message/sendText/'.$this->connection->instance), [
            'number' => self::normalizeNumber($number),
            'text' => $text,
        ]));

        if ($response === null) {
            return ['ok' => false, 'message' => $this->lastError];
        }

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Mensagem enviada.'];
        }

        // Registra para depuração, mas devolve o problema a quem chamou decidir.
        Log::warning('Evolution: envio falhou', ['status' => $response->status(), 'body' => $response->body()]);

        return ['ok' => false, 'message' => $this->reason($response->status(), $response->body())];
    }

    /**
     * Número no formato que a Evolution espera: só dígitos, com país.
     *
     * Os telefones do sistema vêm mascarados — "(11) 98888-7777" — e o Brasil
     * não é assumido quando o número já traz outro código de país.
     */
    public static function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        // 10 ou 11 dígitos é número nacional sem o 55 na frente.
        return strlen($digits) <= 11 ? '55'.$digits : $digits;
    }

    /** Traduz o que deu errado, sem despejar corpo de resposta na tela. */
    private function reason(int $status, string $body): string
    {
        return match (true) {
            $status === 401 || $status === 403 => 'O servidor recusou a chave de API.',
            $status === 404 => 'Instância não encontrada no servidor.',
            $status >= 500 => 'O servidor Evolution respondeu com erro.',
            default => 'Não foi possível falar com o servidor ('.$status.').',
        };
    }
}
