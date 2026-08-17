<?php

namespace App\Support;

/**
 * Vocabulário de permissões do sistema.
 *
 * Permissão é por módulo (a "página") e tem três níveis: sem acesso, leitura, e
 * leitura com escrita. O nível exigido não é declarado rota a rota — sai do
 * método HTTP, porque a regra é sempre a mesma: GET lê, o resto escreve.
 *
 * O módulo também sai do nome da rota (`clientes.index` -> `clientes`), então
 * criar um módulo novo já nasce protegido, sem ninguém precisar lembrar de
 * registrar nada aqui.
 */
final class Permissions
{
    public const NONE = 'none';

    public const READ = 'read';

    public const WRITE = 'write';

    /** @var list<string> */
    public const LEVELS = [self::NONE, self::READ, self::WRITE];

    /**
     * Módulos controlados, na ordem em que aparecem na tela de permissões.
     *
     * @var array<string, string>
     */
    public const MODULES = [
        'dashboard' => 'Dashboard',
        'tarefas' => 'Tarefas',
        'clientes' => 'Clientes',
        'dominios' => 'Domínios',
        'manutencao' => 'Manutenção',
        'contratos' => 'Contratos',
        'financeiro' => 'Financeiro',
        'configuracoes' => 'Configurações',
    ];

    /**
     * Rotas da própria conta e da sessão. Ninguém pode ser trancado para fora
     * do próprio perfil, da própria senha ou do botão de sair.
     *
     * @var list<string>
     */
    private const PERSONAL = ['profile.', 'password.', 'appearance', 'logout', 'login', 'home'];

    /** Ordem dos níveis: cada um contém o anterior. */
    private const RANK = [self::NONE => 0, self::READ => 1, self::WRITE => 2];

    /**
     * Módulo que a rota pertence, ou null quando a rota não é de módulo nenhum
     * (conta própria, sessão, arquivos).
     */
    public static function moduleFor(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        foreach (self::PERSONAL as $personal) {
            if ($routeName === $personal || str_starts_with($routeName, $personal)) {
                return null;
            }
        }

        $module = explode('.', $routeName)[0];

        return array_key_exists($module, self::MODULES) ? $module : null;
    }

    /** GET e HEAD leem; qualquer outro método escreve. */
    public static function levelFor(string $method): string
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true) ? self::READ : self::WRITE;
    }

    /** O nível concedido cobre o exigido? */
    public static function satisfies(?string $granted, string $required): bool
    {
        return (self::RANK[$granted] ?? 0) >= (self::RANK[$required] ?? 0);
    }

    /**
     * Normaliza um mapa vindo de fora: só módulos conhecidos, só níveis válidos.
     *
     * @param  array<string, mixed>  $permissions
     * @return array<string, string>
     */
    public static function sanitize(array $permissions): array
    {
        $clean = [];

        foreach (self::MODULES as $module => $label) {
            $level = $permissions[$module] ?? self::NONE;

            $clean[$module] = in_array($level, self::LEVELS, true) ? $level : self::NONE;
        }

        return $clean;
    }

    /**
     * @return array<string, string>
     */
    public static function all(string $level): array
    {
        return array_fill_keys(array_keys(self::MODULES), $level);
    }
}
