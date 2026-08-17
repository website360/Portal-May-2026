<?php

namespace App\Support;

/**
 * Valor por extenso, como manda o contrato brasileiro:
 * "R$ 1.500,00 (mil e quinhentos reais)".
 */
final class Money
{
    /** @var array<int, string> */
    private const UNITS = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];

    /** @var array<int, string> */
    private const TEENS = [
        'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove',
    ];

    /** @var array<int, string> */
    private const TENS = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];

    /** "cem" é exatamente 100; a partir de 101 vira "cento e". */
    private const HUNDREDS = [
        '', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos',
    ];

    /** @var array<int, array{0: string, 1: string}> */
    private const SCALES = [
        1 => ['mil', 'mil'],
        2 => ['milhão', 'milhões'],
        3 => ['bilhão', 'bilhões'],
    ];

    public static function format(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    /** "R$ 1.500,00 (mil e quinhentos reais)". */
    public static function formatWithWords(float $value): string
    {
        return self::format($value).' ('.self::inWords($value).')';
    }

    public static function inWords(float $value): string
    {
        // Arredonda antes de separar: 0.005 em float viraria 0 centavos.
        $total = (int) round($value * 100);
        $reais = intdiv($total, 100);
        $cents = $total % 100;

        $parts = [];

        if ($reais > 0) {
            /*
             * "um milhão DE reais", e não "um milhão reais": a partir de milhão
             * redondo a moeda entra com preposição. Só quando é redondo —
             * "um milhão e quinhentos mil reais" não leva o "de".
             */
            $moeda = $reais === 1 ? 'real' : 'reais';
            $preposicao = $reais >= 1_000_000 && $reais % 1_000_000 === 0 ? 'de ' : '';

            $parts[] = self::integerInWords($reais).' '.$preposicao.$moeda;
        }

        if ($cents > 0) {
            $parts[] = self::integerInWords($cents).' '.($cents === 1 ? 'centavo' : 'centavos');
        }

        return $parts === [] ? 'zero real' : implode(' e ', $parts);
    }

    private static function integerInWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        // Quebra em grupos de três, do menos para o mais significativo.
        $groups = [];

        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $words = [];
        $lastGroup = 0;

        foreach (array_reverse($groups, true) as $scale => $group) {
            if ($group === 0) {
                continue;
            }

            // "mil" e não "um mil"; já "um milhão" leva o "um".
            $chunk = $scale === 1 && $group === 1 ? '' : self::hundredsInWords($group);

            if ($scale > 0) {
                [$singular, $plural] = self::SCALES[$scale];
                $chunk = trim($chunk.' '.($group === 1 ? $singular : $plural));
            }

            $words[] = $chunk;
            $lastGroup = $group;
        }

        return self::join($words, $lastGroup);
    }

    private static function hundredsInWords(int $number): string
    {
        if ($number === 100) {
            return 'cem';
        }

        $hundreds = intdiv($number, 100);
        $rest = $number % 100;

        $parts = [];

        if ($hundreds > 0) {
            $parts[] = self::HUNDREDS[$hundreds];
        }

        if ($rest >= 10 && $rest <= 19) {
            $parts[] = self::TEENS[$rest - 10];
        } else {
            $tens = intdiv($rest, 10);
            $units = $rest % 10;

            if ($tens > 0) {
                $parts[] = self::TENS[$tens];
            }

            if ($units > 0) {
                $parts[] = self::UNITS[$units];
            }
        }

        return implode(' e ', $parts);
    }

    /**
     * Junta os grupos como se fala.
     *
     * O último grupo entra com "e" quando é menor que cem ou é centena redonda
     * — "mil e quinze", "mil e quinhentos". Quando não é, entra com vírgula,
     * porque o "e" já está sendo usado dentro do próprio grupo:
     * "mil, duzentos e trinta" (e não "mil e duzentos e trinta").
     *
     * @param  list<string>  $words
     * @param  int  $lastGroup  valor numérico do último grupo, que decide o "e"
     */
    private static function join(array $words, int $lastGroup): string
    {
        if (count($words) === 1) {
            return $words[0];
        }

        $last = array_pop($words);
        $separator = $lastGroup < 100 || $lastGroup % 100 === 0 ? ' e ' : ', ';

        return implode(', ', $words).$separator.$last;
    }
}
