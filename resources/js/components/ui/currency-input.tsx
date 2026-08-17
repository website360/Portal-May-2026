import { Input, type InputProps } from '@/components/ui/input';
import { currencyToNumber, maskCurrency, numberToCurrency } from '@/lib/masks';
import { cn } from '@/lib/utils';
import { useEffect, useState } from 'react';

interface CurrencyInputProps extends Omit<InputProps, 'value' | 'onChange' | 'type'> {
    /** Valor numérico como o servidor entende: "1234.56". Vazio = sem valor. */
    value: string;
    /** Devolve sempre o numérico, nunca o texto com máscara. */
    onChange: (value: string) => void;
}

/**
 * Campo de dinheiro.
 *
 * Mostra "1.234,56" e devolve "1234.56": o formulário e o servidor continuam
 * lidando com número, e a máscara não vaza para fora daqui. O texto exibido vive
 * em estado próprio porque digitar "1" precisa virar "0,01" na tela sem que o
 * valor numérico do formulário passe por estados intermediários estranhos.
 */
export function CurrencyInput({ value, onChange, className, ...props }: CurrencyInputProps) {
    const [display, setDisplay] = useState(() => numberToCurrency(value));

    /*
     * Ressincroniza quando o valor muda por fora — abrir a gaveta em outro
     * lançamento, por exemplo. Comparar pelo numérico evita que o próprio
     * onChange desfaça o que a pessoa acabou de digitar.
     */
    useEffect(() => {
        if (currencyToNumber(display) !== value) {
            setDisplay(numberToCurrency(value));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    return (
        <div className="relative">
            {/* Alinhado ao mesmo eixo do startIcon do Input, para os campos da
                mesma coluna começarem no mesmo ponto. */}
            <span className="text-muted-foreground/70 pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm leading-none">R$</span>

            <Input
                {...props}
                type="text"
                inputMode="numeric"
                value={display}
                onChange={(event) => {
                    const masked = maskCurrency(event.target.value);

                    setDisplay(masked);
                    onChange(currencyToNumber(masked));
                }}
                className={cn('tabular pl-10 text-right', className)}
            />
        </div>
    );
}
