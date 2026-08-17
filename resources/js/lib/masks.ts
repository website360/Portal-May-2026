export function onlyDigits(value: string): string {
    return value.replace(/\D/g, '');
}

export function maskCnpj(value: string): string {
    const digits = onlyDigits(value).slice(0, 14);

    let masked = digits.slice(0, 2);
    if (digits.length > 2) masked += `.${digits.slice(2, 5)}`;
    if (digits.length > 5) masked += `.${digits.slice(5, 8)}`;
    if (digits.length > 8) masked += `/${digits.slice(8, 12)}`;
    if (digits.length > 12) masked += `-${digits.slice(12, 14)}`;

    return masked;
}

export function maskCpf(value: string): string {
    const digits = onlyDigits(value).slice(0, 11);

    let masked = digits.slice(0, 3);
    if (digits.length > 3) masked += `.${digits.slice(3, 6)}`;
    if (digits.length > 6) masked += `.${digits.slice(6, 9)}`;
    if (digits.length > 9) masked += `-${digits.slice(9, 11)}`;

    return masked;
}

export function maskDocument(value: string, type: 'company' | 'person'): string {
    return type === 'company' ? maskCnpj(value) : maskCpf(value);
}

/** Celular com 9 digitos e fixo com 8 usam agrupamentos diferentes. */
export function maskPhone(value: string): string {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length === 0) return '';
    if (digits.length <= 2) return `(${digits}`;

    const subscriberLength = digits.length > 10 ? 5 : 4;
    const prefix = digits.slice(2, 2 + subscriberLength);
    const suffix = digits.slice(2 + subscriberLength);

    return suffix ? `(${digits.slice(0, 2)}) ${prefix}-${suffix}` : `(${digits.slice(0, 2)}) ${prefix}`;
}

export function maskZipCode(value: string): string {
    const digits = onlyDigits(value).slice(0, 8);

    return digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits;
}

/**
 * Dinheiro, do jeito que se digita: os centavos entram pela direita.
 *
 * "1" vira 0,01; "150" vira 1,50; "123456" vira 1.234,56. É o comportamento de
 * caixa de supermercado, e evita a dúvida de onde fica a vírgula quando o campo
 * aceita texto livre.
 */
export function maskCurrency(value: string): string {
    // 13 dígitos: até 99 bilhões, muito além do que a agência movimenta, e
    // dentro do que um float aguenta sem perder centavo.
    const digits = onlyDigits(value).slice(0, 13);

    if (digits === '') {
        return '';
    }

    const padded = digits.padStart(3, '0');
    const cents = padded.slice(-2);
    const units = padded.slice(0, -2).replace(/^0+(?=\d)/, '');

    return `${units.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${cents}`;
}

/** Da máscara para o que o servidor espera: "1.234,56" -> "1234.56". */
export function currencyToNumber(masked: string): string {
    const digits = onlyDigits(masked);

    return digits === '' ? '' : (parseInt(digits, 10) / 100).toFixed(2);
}

/** Do que veio do servidor para a máscara: 1234.5 -> "1.234,50". */
export function numberToCurrency(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const parsed = typeof value === 'number' ? value : parseFloat(value);

    if (Number.isNaN(parsed)) {
        return '';
    }

    // Arredonda em centavos antes de virar string, senão 0.1+0.2 do JavaScript
    // vira "0,30000000000000004".
    return maskCurrency(String(Math.round(parsed * 100)));
}
