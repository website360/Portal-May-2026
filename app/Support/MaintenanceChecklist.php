<?php

namespace App\Support;

/**
 * O que se confere na manutenção mensal de um site.
 *
 * A lista mora aqui, e não no banco, porque é a mesma para todo cliente e muda
 * junto com o processo da agência — não é dado que cada um cadastra. Cada
 * manutenção registrada guarda a própria cópia dos rótulos, então mexer nesta
 * lista muda o que se pede daqui para a frente sem reescrever o histórico.
 */
final class MaintenanceChecklist
{
    public const DONE = 'done';

    public const NOT_NEEDED = 'not_needed';

    public const SKIPPED = 'skipped';

    /**
     * Os três resultados possíveis por item.
     *
     * "Não necessário" e "Pular" parecem iguais e não são: o primeiro é uma
     * conclusão — foi olhado e não havia o que fazer; o segundo é uma pendência
     * — não foi olhado. Por isso só o primeiro aparece no relatório do cliente.
     *
     * @var array<string, string>
     */
    public const RESULTS = [
        self::DONE => 'Realizado',
        self::NOT_NEEDED => 'Não necessário',
        self::SKIPPED => 'Pular',
    ];

    /**
     * @var array<string, string>
     */
    public const ITEMS = [
        'site' => 'Atualização Site',
        'plugins' => 'Atualização Plugins',
        'tema' => 'Atualização Tema',
        'backup' => 'Backup',
        'licencas' => 'Renovação das Licenças Pro',
        'correcoes_pre' => 'Correções pré atualização',
        'correcoes_pos' => 'Correções pós atualização',
        'instalacoes' => 'Instalações',
        'configuracoes' => 'Configurações',
        'performance' => 'Melhoria na performance',
        'seguranca' => 'Melhoria na segurança',
    ];

    /**
     * A lista em branco que a tela abre — tudo como realizado, que é o caso
     * comum. Quem executou tira o que não se aplica, em vez de marcar onze itens.
     *
     * @return list<array{key: string, label: string, result: string}>
     */
    public static function blank(): array
    {
        return self::from([]);
    }

    /**
     * Monta a lista completa a partir de um mapa `chave => resultado` vindo da
     * tela. Chave desconhecida é ignorada; resultado inválido vira "realizado".
     *
     * @param  array<string, mixed>  $results
     * @return list<array{key: string, label: string, result: string}>
     */
    public static function from(array $results): array
    {
        $items = [];

        foreach (self::ITEMS as $key => $label) {
            $result = $results[$key] ?? self::DONE;

            $items[] = [
                'key' => $key,
                'label' => $label,
                'result' => array_key_exists($result, self::RESULTS) ? $result : self::DONE,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{key: string, label: string, result: string}>  $items
     */
    public static function countOf(array $items, string $result): int
    {
        return count(array_filter($items, fn (array $item) => ($item['result'] ?? null) === $result));
    }
}
