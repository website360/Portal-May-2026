<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente do Asaas — a plataforma de cobrança da agência.
 *
 * Usado só para conciliar: conferir o que já foi pago e casar as cobranças dos
 * dois lados. A chave e o ambiente ficam num arquivo (storage/app/asaas.json),
 * fora da web, porque criar tabela está fora do meu alcance aqui. Nenhum método
 * lança: uma API externa fora do ar não pode derrubar a tela.
 */
final class Asaas
{
    private static function file(): string
    {
        return storage_path('app/asaas.json');
    }

    /**
     * @return array{api_key: string, environment: string}
     */
    public static function config(): array
    {
        $defaults = ['api_key' => '', 'environment' => 'production'];

        if (! is_file(self::file())) {
            return $defaults;
        }

        $d = json_decode((string) file_get_contents(self::file()), true);

        return is_array($d) ? array_merge($defaults, array_intersect_key($d, $defaults)) : $defaults;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        $merged = array_merge(self::config(), array_intersect_key($data, ['api_key' => '', 'environment' => '']));

        file_put_contents(self::file(), json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function configured(): bool
    {
        return filled(self::config()['api_key']);
    }

    public static function baseUrl(): string
    {
        return self::config()['environment'] === 'sandbox'
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/v3';
    }

    private static function http(): PendingRequest
    {
        return Http::withHeaders(['access_token' => self::config()['api_key']])->acceptJson()->timeout(25);
    }

    /**
     * Confere se a chave fala com o Asaas.
     *
     * @return array{ok: bool, message: string}
     */
    public static function test(): array
    {
        if (! self::configured()) {
            return ['ok' => false, 'message' => 'Cadastre a chave de API antes de testar.'];
        }

        try {
            $response = self::http()->get(self::baseUrl().'/customers', ['limit' => 1]);
        } catch (\Throwable $e) {
            Log::warning('Asaas: falha de conexão', ['erro' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Não foi possível alcançar o Asaas. Verifique a conexão do servidor.'];
        }

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Conectado ao Asaas.'];
        }

        return ['ok' => false, 'message' => match ($response->status()) {
            401 => 'O Asaas recusou a chave (401). Confira a chave e o ambiente (produção x sandbox).',
            default => 'O Asaas respondeu com erro (HTTP '.$response->status().').',
        }];
    }

    /**
     * Uma chamada GET genérica ao Asaas, devolvendo o JSON ou null em falha.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public static function get(string $path, array $query = []): ?array
    {
        try {
            $response = self::http()->get(self::baseUrl().$path, $query);
        } catch (\Throwable $e) {
            Log::warning('Asaas: GET falhou', ['path' => $path, 'erro' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Busca uma listagem paginada até esgotar (com teto de segurança).
     *
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public static function paginate(string $path, array $query = [], int $max = 1000): array
    {
        $items = [];
        $offset = 0;
        $limit = 100;

        do {
            $page = self::get($path, array_merge($query, ['offset' => $offset, 'limit' => $limit]));

            if ($page === null) {
                break;
            }

            $items = array_merge($items, $page['data'] ?? []);
            $hasMore = (bool) ($page['hasMore'] ?? false);
            $offset += $limit;
        } while ($hasMore && count($items) < $max);

        return $items;
    }

    /**
     * POST genérico ao Asaas.
     *
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, message: string, data: array<string, mixed>|null}
     */
    public static function post(string $path, array $body): array
    {
        try {
            $response = self::http()->post(self::baseUrl().$path, $body);
        } catch (\Throwable $e) {
            Log::warning('Asaas: POST falhou', ['path' => $path, 'erro' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Não foi possível alcançar o Asaas.', 'data' => null];
        }

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'ok', 'data' => $response->json()];
        }

        $detail = $response->json('errors.0.description');

        return ['ok' => false, 'message' => is_string($detail) && $detail !== '' ? $detail : ('HTTP '.$response->status()), 'data' => null];
    }

    /**
     * Acha o cliente no Asaas pelo CPF/CNPJ, ou cria um. Devolve o id, ou null.
     */
    public static function customerFor(string $cpfCnpj, string $name, ?string $email = null, ?string $phone = null): ?string
    {
        $digits = preg_replace('/\D/', '', $cpfCnpj) ?? '';

        if ($digits === '') {
            return null;
        }

        $found = self::get('/customers', ['cpfCnpj' => $digits]);

        if ($found !== null && ! empty($found['data'])) {
            return $found['data'][0]['id'] ?? null;
        }

        $created = self::post('/customers', array_filter([
            'name' => $name,
            'cpfCnpj' => $digits,
            'email' => $email,
            'mobilePhone' => $phone !== null ? (preg_replace('/\D/', '', $phone) ?: null) : null,
        ]));

        return $created['ok'] ? ($created['data']['id'] ?? null) : null;
    }
}
