<?php

namespace App\Support;

/**
 * O registro das cobranças: o status por fatura (para a listagem) e o histórico
 * de tudo que já saiu (para o relatório).
 *
 * Guardado num arquivo JSON (storage/app/charges.json), e não em tabela nova,
 * para não depender de mudança de schema. Formato:
 *   { "statuses": { "<id>": {charged_at, error} }, "log": [ {...evento} ] }
 */
final class Charges
{
    private static function file(): string
    {
        return storage_path('app/charges.json');
    }

    /**
     * @return array{statuses: array<string, mixed>, log: list<array<string, mixed>>}
     */
    private static function data(): array
    {
        $base = ['statuses' => [], 'log' => []];

        if (! is_file(self::file())) {
            return $base;
        }

        $data = json_decode((string) file_get_contents(self::file()), true);

        return is_array($data) ? array_merge($base, array_intersect_key($data, $base)) : $base;
    }

    private static function put(array $data): void
    {
        file_put_contents(self::file(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Status de uma fatura: quando foi cobrada e o erro da última tentativa.
     *
     * @return array{charged_at: ?string, error: ?string}|null
     */
    public static function forId(int $id): ?array
    {
        return self::data()['statuses'][(string) $id] ?? null;
    }

    /**
     * Registra uma tentativa: atualiza o status da fatura e acrescenta ao log.
     *
     * @param  array<string, mixed>  $meta  dados do evento para o histórico
     */
    public static function record(int $id, bool $ok, string $message, array $meta = []): void
    {
        $data = self::data();
        $anterior = $data['statuses'][(string) $id]['charged_at'] ?? null;

        $data['statuses'][(string) $id] = [
            // Falha não apaga um "cobrado" anterior.
            'charged_at' => $ok ? now()->format('d/m/Y H:i') : $anterior,
            'error' => $ok ? null : $message,
        ];

        $data['log'][] = array_merge($meta, [
            'transaction_id' => $id,
            'ok' => $ok,
            'message' => $message,
            'at' => now()->format('d/m/Y H:i'),
        ]);

        // O histórico não cresce sem limite: guarda os últimos 500 eventos.
        if (count($data['log']) > 500) {
            $data['log'] = array_slice($data['log'], -500);
        }

        self::put($data);
    }

    /**
     * O histórico, do mais recente para o mais antigo.
     *
     * @return list<array<string, mixed>>
     */
    public static function history(): array
    {
        return array_reverse(self::data()['log']);
    }
}
