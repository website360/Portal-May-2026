<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Quanto virou um boleto pago fora do prazo.
 *
 * A praxe brasileira, e o que o Código de Defesa do Consumidor limita para
 * consumo: multa de até 2% aplicada uma vez, e juros de mora de até 1% ao mês
 * calculados por dia corrido — o "pro rata die". Aqui os percentuais são
 * livres, porque contrato entre empresas pode combinar outros.
 */
final class LateFee
{
    /** Os dias que a praxe usa para ratear o juro mensal. */
    private const DAYS_IN_MONTH = 30;

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
        float $finePercent = 2.0,
        float $monthlyInterest = 1.0,
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
