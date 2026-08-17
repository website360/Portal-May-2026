import { LucideIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

export interface InputProps extends React.ComponentProps<'input'> {
    /** Icone fixo a esquerda do campo. O input ganha pl-10 para nao passar por cima. */
    startIcon?: LucideIcon;
}

/**
 * O ícone do seletor de data fica boiando entre o texto e a borda — o navegador
 * o solta logo depois dos dígitos e deixa o resto do campo vazio. Preso à
 * direita, alinha com os outros afordances do sistema, e sai do preto puro para
 * o mesmo tom dos demais ícones.
 *
 * Só para data: `relative` no input o faz pintar por cima de um startIcon
 * absoluto, e aplicar isso em todo campo apagaria os ícones do sistema inteiro.
 */
const dateIndicator = [
    'relative',
    '[&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:top-1/2',
    '[&::-webkit-calendar-picker-indicator]:right-3 [&::-webkit-calendar-picker-indicator]:-translate-y-1/2',
    '[&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:opacity-50',
    '[&::-webkit-calendar-picker-indicator]:hover:opacity-100',
].join(' ');

const Input = React.forwardRef<HTMLInputElement, InputProps>(({ className, type, startIcon: StartIcon, ...props }, ref) => {
    const input = (
        <input
            type={type}
            className={cn(
                'flex h-9 w-full rounded-lg border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors',
                'file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground',
                'placeholder:text-muted-foreground/70',
                'focus-visible:border-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary/20',
                'disabled:cursor-not-allowed disabled:opacity-50',
                (type === 'date' || type === 'datetime-local' || type === 'time' || type === 'month') && dateIndicator,
                // 2.5rem: o ícone ocupa até 1.75rem, e o texto precisa de folga
                // depois dele — com pl-9 encostava.
                StartIcon && 'pl-10',
                className,
            )}
            ref={ref}
            {...props}
        />
    );

    if (!StartIcon) {
        return input;
    }

    return (
        <div className="relative">
            {/*
             * z-10: um input posicionado (o de data é) pintaria por cima deste
             * ícone, que vem antes no DOM. Sem eventos de ponteiro, ficar por
             * cima não atrapalha a digitação.
             */}
            <StartIcon className="text-muted-foreground/70 pointer-events-none absolute top-1/2 left-3 z-10 size-4 -translate-y-1/2" />
            {input}
        </div>
    );
});

Input.displayName = 'Input';

export { Input };
