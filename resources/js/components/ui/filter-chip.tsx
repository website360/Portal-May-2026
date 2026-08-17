import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

/**
 * Indicador que tambem filtra.
 *
 * Mostrar o numero e deixar clicar no mesmo elemento evita a duplicidade de ter
 * um painel de contagens em cima e um seletor de filtro logo abaixo dizendo a
 * mesma coisa.
 */
export function FilterChip({
    icon: Icon,
    label,
    count,
    active,
    tone,
    onClick,
}: {
    icon: LucideIcon;
    label: string;
    count: number;
    active?: boolean;
    tone?: 'destructive' | 'success';
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-all duration-150',
                'hover:border-primary/30 focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden active:scale-[0.98]',
                active ? 'border-primary bg-accent text-accent-foreground shadow-xs' : 'border-input bg-background',
            )}
        >
            <Icon
                className={cn(
                    'size-4',
                    tone === 'destructive' && count > 0 && 'text-destructive',
                    tone === 'success' && 'text-success',
                    !tone && 'text-muted-foreground',
                )}
            />
            <span>{label}</span>
            <span className="tabular font-semibold">{formatNumber(count)}</span>
        </button>
    );
}
