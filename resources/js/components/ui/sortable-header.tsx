import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';

export type SortDirection = 'asc' | 'desc';

export interface SortState {
    sort: string;
    direction: SortDirection;
}

interface SortableHeaderProps extends SortState {
    /** Chave aceita pelo controller. */
    column: string;
    label: string;
    onSort: (column: string, direction: SortDirection) => void;
    align?: 'left' | 'right';
    className?: string;
}

/**
 * Cabeçalho que ordena. Clicar numa coluna nova ordena crescente; clicar de
 * novo na mesma inverte — sem terceiro estado, para não haver clique que
 * "desliga" a ordenação e deixe a lista sem ordem definida.
 */
export function SortableHeader({ column, label, sort, direction, onSort, align = 'left', className }: SortableHeaderProps) {
    const active = sort === column;
    const Icon = active ? (direction === 'asc' ? ArrowUp : ArrowDown) : ChevronsUpDown;

    return (
        <th className={cn('px-4 py-2.5 font-medium', className)}>
            <button
                type="button"
                onClick={() => onSort(column, active && direction === 'asc' ? 'desc' : 'asc')}
                aria-label={`Ordenar por ${label}`}
                aria-sort={active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}
                className={cn(
                    'group/sort flex w-full cursor-pointer items-center gap-1 rounded-sm transition-colors',
                    'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden',
                    align === 'right' && 'justify-end',
                    active ? 'text-foreground' : 'hover:text-foreground',
                )}
            >
                {label}
                <Icon className={cn('size-3 shrink-0 transition-opacity', active ? 'opacity-100' : 'opacity-0 group-hover/sort:opacity-60')} />
            </button>
        </th>
    );
}
