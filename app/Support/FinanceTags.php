<?php

namespace App\Support;

/**
 * Etiquetas do financeiro: um catálogo de rótulos e quais lançamentos têm cada um.
 *
 * Diferente da categoria (uma só por lançamento), a etiqueta é livre e múltipla
 * — serve para cruzar filtros e relatórios ("marketing" + "recorrente", por
 * exemplo). Guardado em arquivo (storage/app/finance_tags.json), sem tabela nova.
 *
 * Formato: { "tags": [{id, name, color}], "assignments": { "<txId>": [tagId, ...] } }
 */
final class FinanceTags
{
    private static function file(): string
    {
        return storage_path('app/finance_tags.json');
    }

    /**
     * @return array{tags: list<array{id:int,name:string,color:string}>, assignments: array<string, list<int>>}
     */
    private static function data(): array
    {
        $base = ['tags' => [], 'assignments' => []];

        if (! is_file(self::file())) {
            return $base;
        }

        $d = json_decode((string) file_get_contents(self::file()), true);

        return is_array($d) ? array_merge($base, array_intersect_key($d, $base)) : $base;
    }

    private static function put(array $d): void
    {
        file_put_contents(self::file(), json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @return list<array{id:int,name:string,color:string}>
     */
    public static function all(): array
    {
        return array_values(self::data()['tags']);
    }

    public static function create(string $name, string $color): void
    {
        $d = self::data();
        $id = (int) (max([0, ...array_column($d['tags'], 'id')]) + 1);
        $d['tags'][] = ['id' => $id, 'name' => $name, 'color' => $color];
        self::put($d);
    }

    public static function update(int $id, string $name, string $color): void
    {
        $d = self::data();

        foreach ($d['tags'] as &$t) {
            if ($t['id'] === $id) {
                $t['name'] = $name;
                $t['color'] = $color;
            }
        }

        self::put($d);
    }

    public static function delete(int $id): void
    {
        $d = self::data();
        $d['tags'] = array_values(array_filter($d['tags'], fn ($t) => $t['id'] !== $id));

        foreach ($d['assignments'] as $tx => $ids) {
            $novos = array_values(array_filter(array_map('intval', $ids), fn ($i) => $i !== $id));

            if ($novos === []) {
                unset($d['assignments'][$tx]);
            } else {
                $d['assignments'][$tx] = $novos;
            }
        }

        self::put($d);
    }

    public static function exists(int $id): bool
    {
        return in_array($id, array_column(self::data()['tags'], 'id'), true);
    }

    /**
     * @return list<int>
     */
    public static function forTransaction(int $txId): array
    {
        return array_map('intval', self::data()['assignments'][(string) $txId] ?? []);
    }

    /**
     * As etiquetas (objeto completo) de um lançamento, na ordem do catálogo.
     *
     * @return list<array{id:int,name:string,color:string}>
     */
    public static function tagsOf(int $txId): array
    {
        $ids = self::forTransaction($txId);

        return array_values(array_filter(self::all(), fn ($t) => in_array($t['id'], $ids, true)));
    }

    /**
     * @param  list<int|string>  $tagIds
     */
    public static function setForTransaction(int $txId, array $tagIds): void
    {
        $d = self::data();
        $validos = array_column($d['tags'], 'id');
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $tagIds),
            fn ($i) => in_array($i, $validos, true),
        )));

        if ($ids === []) {
            unset($d['assignments'][(string) $txId]);
        } else {
            $d['assignments'][(string) $txId] = $ids;
        }

        self::put($d);
    }

    /**
     * Ids de lançamentos que têm QUALQUER uma das etiquetas pedidas.
     *
     * @param  list<int|string>  $tagIds
     * @return list<int>
     */
    public static function transactionIdsWith(array $tagIds): array
    {
        $tagIds = array_map('intval', $tagIds);
        $ids = [];

        foreach (self::data()['assignments'] as $tx => $assigned) {
            if (array_intersect($tagIds, array_map('intval', $assigned)) !== []) {
                $ids[] = (int) $tx;
            }
        }

        return $ids;
    }
}
