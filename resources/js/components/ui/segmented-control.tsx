import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

export interface SegmentedOption {
    value: string;
    label: string;
    icon?: LucideIcon;
    /** Classes aplicadas só quando a opção está ativa, para carregar o tom dela. */
    activeClassName?: string;
}

interface SegmentedControlProps {
    value: string;
    onChange: (value: string) => void;
    options: SegmentedOption[];
    id?: string;
    className?: string;
    'aria-label'?: string;
}

/**
 * Escolha entre poucas opções sem abrir menu: tudo visível, um clique. Para
 * listas longas use o Combobox.
 */
export function SegmentedControl({ value, onChange, options, id, className, ...rest }: SegmentedControlProps) {
    return (
        <div id={id} role="radiogroup" aria-label={rest['aria-label']} className={cn('bg-muted flex gap-1 rounded-lg p-1', className)}>
            {options.map((option) => {
                const Icon = option.icon;
                const active = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium whitespace-nowrap transition-all duration-150',
                            'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden active:scale-[0.97]',
                            active
                                ? cn('bg-background shadow-xs', option.activeClassName ?? 'text-foreground')
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {Icon && <Icon className="size-3.5 shrink-0" />}
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
