<?php

namespace App\Support;

use App\Models\WhatsappConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cliente do Evolution Go.
 *
 * Concentra aqui todo o conhecimento sobre o formato da API: os módulos que
 * forem mandar mensagem pedem "envie para este número", e não precisam saber de
 * rota, cabeçalho nem formato de resposta.
 *
 * A autenticação tem dois níveis, e é a parte que mais confunde:
 *
 *   - a **chave global**, cadastrada na tela, só gerencia instâncias — listar,
 *     criar, excluir;
 *   - cada instância tem um **token próprio**, definido por nós na criação, e é
 *     ele que autentica tudo que é da instância: pedir QR, consultar estado,
 *     enviar mensagem.
 *
 * Usar a chave global numa chamada de instância devolve 401, e o contrário
 * também. Foi o que fez a primeira versão deste cliente falhar inteira.
 *
 * Nenhum método lança exceção por falha de rede ou resposta de erro — eles
 * devolvem um resultado que diz o que houve. Uma integração externa fora do ar
 * não pode derrubar a tela de quem só queria salvar um cliente.
 */
final class Evolution
{
    /** O servidor ainda não produziu o código; é estado de espera, não falha. */
    public const QR_PENDING = 'pending';

    private ?string $lastError = null;

    public function __construct(private readonly WhatsappConnection $connection) {}

    /** Chamadas de gerenciamento: listar e criar instâncias. */
    private function global(): PendingRequest
    {
        return $this->http((string) $this->connection->api_key);
    }

    /** Chamadas da instância: QR, estado, envio. */
    private function instance(): PendingRequest
    {
        return $this->http((string) $this->connection->instance_token);
    }

    private function http(string $key): PendingRequest
    {
        return Http::withHeaders(['apikey' => $key])->acceptJson()->timeout(20);
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
     * Garante que a instância existe no servidor e que temos o token dela.
     *
     * A lista devolve o token de cada instância, então uma criada por fora —
     * pela tela do próprio Evolution, por exemplo — é adotada em vez de
     * duplicada.
     *
     * @return array{ok: bool, message: string}
     */
    public function ensureInstance(): array
    {
        $found = $this->findInstance();

        if ($found === null && $this->lastError !== null) {
            return ['ok' => false, 'message' => $this->lastError];
        }

        if ($found !== null) {
            $this->remember($found);

            return ['ok' => true, 'message' => 'Instância pronta.'];
        }

        /*
         * O token é nosso: o servidor exige recebê-lo na criação e não gera um
         * sozinho — criar sem ele responde "token is required".
         */
        $response = $this->attempt(fn () => $this->global()->post($this->connection->url('/instance/create'), [
            'name' => $this->connection->instance,
            'token' => (string) Str::uuid(),
        ]));

        if ($response === null) {
            return ['ok' => false, 'message' => $this->lastError];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => $this->reason($response->status(), $response->body())];
        }

        $this->remember($response->json('data') ?? []);

        return ['ok' => true, 'message' => 'Instância criada.'];
    }

    /**
     * A instância com o nome configurado, ou null quando ainda não existe.
     *
     * @return array<string, mixed>|null
     */
    private function findInstance(): ?array
    {
        $this->lastError = null;

        $response = $this->attempt(fn () => $this->global()->get($this->connection->url('/instance/all')));

        if ($response === null) {
            return null;
        }

        if (! $response->successful()) {
            $this->lastError = $this->reason($response->status(), $response->body());

            return null;
        }

        foreach ($response->json('data') ?? [] as $instance) {
            if (($instance['name'] ?? null) === $this->connection->instance) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $instance
     */
    private function remember(array $instance): void
    {
        $novo = array_filter([
            'instance_token' => $instance['token'] ?? null,
            'instance_id' => $instance['id'] ?? null,
        ]);

        if ($novo !== []) {
            $this->connection->forceFill($novo)->save();
        }
    }

    /**
     * Pede o QR Code para parear o aparelho.
     *
     * São dois passos no Evolution Go: `connect` abre a sessão e `qr` busca o
     * código. Entre um e outro o servidor leva um instante, e nesse intervalo
     * responde 400 — que aqui é espera, não erro, e a tela continua pedindo.
     *
     * @return array{ok: bool, qr: ?string, message: string, state?: string}
     */
    public function qrCode(): array
    {
        $created = $this->ensureInstance();

        if (! $created['ok']) {
            return ['ok' => false, 'qr' => null, 'message' => $created['message']];
        }

        $connect = $this->attempt(fn () => $this->instance()->post($this->connection->url('/instance/connect'), [
            'immediate' => true,
        ]));

        if ($connect === null) {
            return ['ok' => false, 'qr' => null, 'message' => $this->lastError];
        }

        if (! $connect->successful()) {
            return ['ok' => false, 'qr' => null, 'message' => $this->reason($connect->status(), $connect->body())];
        }

        $response = $this->attempt(fn () => $this->instance()->get($this->connection->url('/instance/qr')));

        if ($response === null) {
            return ['ok' => false, 'qr' => null, 'message' => $this->lastError];
        }

        if ($response->successful()) {
            $qr = $this->extractQr($response->json());

            return $qr === null ? $this->waiting() : ['ok' => true, 'qr' => $qr, 'message' => 'Leia o código no WhatsApp do aparelho.'];
        }

        /*
         * "no QR code available" é o servidor dizendo "ainda não" — devolver
         * isso como falha faria a tela parar de tentar justo quando faltava
         * esperar mais um instante.
         */
        if ($response->status() === 400 && str_contains($response->body(), 'no QR code available')) {
            return $this->waiting();
        }

        return ['ok' => false, 'qr' => null, 'message' => $this->reason($response->status(), $response->body())];
    }

    /**
     * @return array{ok: bool, qr: null, state: string, message: string}
     */
    private function waiting(): array
    {
        return [
            'ok' => true,
            'qr' => null,
            'state' => self::QR_PENDING,
            'message' => 'O servidor está abrindo a sessão. O código aparece em alguns segundos.',
        ];
    }

    /**
     * O código pode vir em chaves diferentes conforme a versão, e às vezes já
     * como imagem embutida, às vezes como o texto puro do QR.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function extractQr(?array $payload): ?string
    {
        $data = $payload['data'] ?? $payload ?? [];

        if (! is_array($data)) {
            return null;
        }

        foreach (['qrcode', 'qr', 'base64', 'code'] as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return str_starts_with($value, 'data:') ? $value : 'data:image/png;base64,'.$value;
            }
        }

        return null;
    }

    /**
     * Estado atual da conexão, direto do servidor.
     *
     * @return array{ok: bool, status: string, number: ?string, message: string}
     */
    public function state(): array
    {
        if (blank($this->connection->instance_token)) {
            $ready = $this->ensureInstance();

            if (! $ready['ok']) {
                return ['ok' => false, 'status' => WhatsappConnection::STATUS_DISCONNECTED, 'number' => null, 'message' => $ready['message']];
            }
        }

        $response = $this->attempt(fn () => $this->instance()->get($this->connection->url('/instance/status')));

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

        // O Evolution Go responde com iniciais maiúsculas: Connected, LoggedIn.
        $data = $response->json('data') ?? [];
        $connected = (bool) ($data['Connected'] ?? false);
        $logged = (bool) ($data['LoggedIn'] ?? false);

        return [
            'ok' => true,
            'status' => match (true) {
                $connected && $logged => WhatsappConnection::STATUS_CONNECTED,
                $connected => WhatsappConnection::STATUS_CONNECTING,
                default => WhatsappConnection::STATUS_DISCONNECTED,
            },
            'number' => $this->numberFrom($data),
            'message' => 'Estado consultado.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function numberFrom(array $data): ?string
    {
        $jid = $data['Jid'] ?? $data['jid'] ?? $data['Name'] ?? null;

        if (! is_string($jid) || $jid === '') {
            return null;
        }

        // O JID vem como "5511999998888:12@s.whatsapp.net".
        return explode(':', explode('@', $jid)[0])[0];
    }

    /** Desconecta o aparelho sem apagar a instância. */
    public function logout(): array
    {
        $response = $this->attempt(fn () => $this->instance()->delete($this->connection->url('/instance/logout')));

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
        if (blank($this->connection->instance_token)) {
            $ready = $this->ensureInstance();

            if (! $ready['ok']) {
                return ['ok' => false, 'message' => $ready['message']];
            }
        }

        $response = $this->attempt(fn () => $this->instance()->post($this->connection->url('/send/text'), [
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
     * Número no formato que o servidor espera: só dígitos, com país.
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

    /**
     * Traduz o que deu errado, sem despejar corpo de resposta na tela.
     *
     * A explicação do servidor entra quando ele manda uma em `error` — o
     * Evolution Go manda, e costuma ser mais útil que qualquer texto meu.
     */
    private function reason(int $status, string $body): string
    {
        $detail = json_decode($body, true)['error'] ?? null;
        $detail = is_string($detail) && $detail !== '' ? $detail : null;

        return match (true) {
            $status === 401 || $status === 403 => 'O servidor recusou a chave de API.',
            $status === 404 => 'Rota não encontrada no servidor. Confira o endereço cadastrado.',
            $status >= 500 => 'O servidor Evolution respondeu com erro'.($detail ? ": {$detail}" : '.'),
            $detail !== null => "O servidor recusou: {$detail}",
            default => 'Não foi possível falar com o servidor ('.$status.').',
        };
    }
}
