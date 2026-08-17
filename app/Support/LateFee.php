<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Quanto virou um boleto pago fora do prazo.
 *
 * A praxe brasileira: multa aplicada uma vez e juros de mora de 1% ao mês
 * calculados por dia corrido — o "pro rata die". Os percentuais são livres
 * porque o teto de 2% do Código de Defesa do Consumidor vale para consumo;
 * entre empresas o contrato combina o seu, e o nosso é 5%.
 */
final class LateFee
{
    /** Os dias que a praxe usa para ratear o juro mensal. */
    private const DAYS_IN_MONTH = 30;

    /** A multa dos nossos contratos. */
    public const DEFAULT_FINE = 5.0;

    public const DEFAULT_INTEREST = 1.0;

    /** Um cliente atrasado costuma ter alguns boletos em aberto, não um. */
    public const MAX_INSTALLMENTS = 60;

    /**
     * @return array{
     *     days_late: int,
     *     late: bool,
     *     amount: float,
     *     discount: float,
     *     fine: float,
     *     interest: float,
     *     daily_rate: float,
     *     total: float,
     * }
     */
    public static function calculate(
        float $amount,
        Carbon $due,
        Carbon $paid,
        float $finePercent = self::DEFAULT_FINE,
        float $monthlyInterest = self::DEFAULT_INTEREST,
        float $discount = 0.0,
    ): array {
        $days = self::daysLate($due, $paid);
        $late = $days > 0;

        // A base do acréscimo é o valor devido: o desconto é uma liberalidade
        // sobre o principal, e não muda o que se cobra pelo atraso.
        $fine = $late ? self::round($amount * $finePercent / 100) : 0.0;
        $dailyRate = $monthlyInterest / self::DAYS_IN_MONTH;
        $interest = $late ? self::round($amount * $dailyRate / 100 * $days) : 0.0;

        // Desconto nunca pode virar troco: no máximo zera o que se deve.
        $discount = min(max($discount, 0.0), $amount + $fine + $interest);

        return [
            'days_late' => $days,
            'late' => $late,
            'amount' => self::round($amount),
            'discount' => self::round($discount),
            'fine' => $fine,
            'interest' => $interest,
            'daily_rate' => round($dailyRate, 6),
            'total' => self::round($amount + $fine + $interest - $discount),
        ];
    }

    /**
     * A conta de uma série de boletos mensais.
     *
     * Quem atrasa raramente atrasa um só: o cliente some por uns meses e volta
     * com quatro em aberto. Os vencimentos seguem o dia do primeiro, mês a mês,
     * e cada um acumula o seu próprio atraso — o mais antigo é sempre o que
     * mais pesa.
     *
     * O desconto é um só, abatido do total, e não rateado entre as parcelas:
     * é assim que ele é combinado ("tira cem reais do todo").
     *
     * @return array{
     *     installments: list<array{number: int, due_at: string}&array<string, mixed>>,
     *     totals: array{
     *         count: int,
     *         late_count: int,
     *         amount: float,
     *         fine: float,
     *         interest: float,
     *         discount: float,
     *         total: float,
     *         daily_rate: float,
     *     },
     * }
     */
    public static function schedule(
        float $amount,
        Carbon $firstDue,
        int $count,
        Carbon $paid,
        float $finePercent = self::DEFAULT_FINE,
        float $monthlyInterest = self::DEFAULT_INTEREST,
        float $discount = 0.0,
    ): array {
        $count = max(1, min($count, self::MAX_INSTALLMENTS));
        $installments = [];

        for ($i = 0; $i < $count; $i++) {
            // addMonthsNoOverflow: um boleto que vence dia 31 cai no dia 28 de
            // fevereiro, e não escorrega para março.
            $due = $firstDue->copy()->addMonthsNoOverflow($i);

            $installments[] = ['number' => $i + 1, 'due_at' => $due->format('Y-m-d')]
                + self::calculate($amount, $due, $paid, $finePercent, $monthlyInterest);
        }

        $somar = fn (string $campo) => self::round(array_sum(array_column($installments, $campo)));

        $devido = $somar('amount') + $somar('fine') + $somar('interest');
        $discount = min(max($discount, 0.0), $devido);

        return [
            'installments' => $installments,
            'totals' => [
                'count' => $count,
                'late_count' => count(array_filter($installments, fn ($p) => $p['late'])),
                'amount' => $somar('amount'),
                'fine' => $somar('fine'),
                'interest' => $somar('interest'),
                'discount' => self::round($discount),
                'total' => self::round($devido - $discount),
                'daily_rate' => round($monthlyInterest / self::DAYS_IN_MONTH, 6),
            ],
        ];
    }

    /**
     * Dias corridos de atraso.
     *
     * Pagar no dia do vencimento é em dia — o dia do vencimento é do devedor.
     * Pagar antes também não gera acréscimo nenhum.
     */
    public static function daysLate(Carbon $due, Carbon $paid): int
    {
        $due = $due->copy()->startOfDay();
        $paid = $paid->copy()->startOfDay();

        return $paid->lessThanOrEqualTo($due) ? 0 : (int) $due->diffInDays($paid);
    }

    /**
     * Arredonda como dinheiro, meio para cima.
     *
     * O arredondamento padrão do PHP em float já erra em casos como 1.005;
     * multiplicar antes evita que centavo suma numa conta que vai virar cobrança.
     */
    private static function round(float $value): float
    {
        return round($value + 0.0000001, 2);
    }
}
