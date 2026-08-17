const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 0,
});

const decimal = new Intl.NumberFormat('pt-BR');

const compact = new Intl.NumberFormat('pt-BR', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

export function formatCurrency(value: number): string {
    return currency.format(value);
}

const exact = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * Valor com os centavos.
 *
 * `formatCurrency` arredonda para inteiro, o que serve para o resumo de um
 * painel e falha em qualquer número que vire cobrança: R$ 1.271,60 saindo como
 * "R$ 1.272" é sessenta centavos inventados.
 */
export function formatMoney(value: number): string {
    return exact.format(value);
}

export function formatNumber(value: number): string {
    return decimal.format(value);
}

/** Eixo Y do grafico: "R$ 120 mil" em vez de "R$ 120.000". */
export function formatCompactCurrency(value: number): string {
    return `R$ ${compact.format(value)}`;
}

export function formatPercent(value: number): string {
    const sign = value > 0 ? '+' : '';

    return `${sign}${decimal.format(Number(value.toFixed(1)))}%`;
}
