<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * A marca da agência que aparece no menu: nome, subtítulo e logo.
 *
 * Guardada num arquivo JSON (storage/app/brand.json), e não no banco, porque é
 * um punhado de valores únicos para o sistema todo — criar uma tabela para uma
 * linha só custaria mais do que rende. Sem o arquivo, valem os padrões.
 */
final class Brand
{
    /** @var list<string> */
    private const KEYS = ['name', 'subtitle', 'logo_path'];

    private static function file(): string
    {
        return storage_path('app/brand.json');
    }

    /**
     * @return array{name: string, subtitle: ?string, logo_path: ?string}
     */
    public static function all(): array
    {
        $defaults = ['name' => (string) config('app.name', 'Sistema May'), 'subtitle' => 'Agência May', 'logo_path' => null];

        if (! is_file(self::file())) {
            return $defaults;
        }

        $data = json_decode((string) file_get_contents(self::file()), true);

        return is_array($data) ? array_merge($defaults, array_intersect_key($data, $defaults)) : $defaults;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        $merged = array_merge(self::all(), array_intersect_key($data, array_flip(self::KEYS)));

        file_put_contents(self::file(), json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * O que vai para a tela: o logo já como URL pública.
     *
     * @return array{name: string, subtitle: ?string, logo_url: ?string}
     */
    public static function shared(): array
    {
        $all = self::all();

        return [
            'name' => $all['name'],
            'subtitle' => $all['subtitle'],
            'logo_url' => $all['logo_path'] ? Storage::disk('public')->url($all['logo_path']) : null,
        ];
    }
}
