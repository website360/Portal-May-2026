import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';

export interface ListSorting {
    sort: string;
    direction: 'asc' | 'desc';
}

/**
 * Ordenacao para listas que nao sao tabela.
 *
 * As telas de configuracao editam cada linha no lugar, entao nao ha cabecalho
 * para clicar — o seletor cumpre o mesmo papel, com o botao ao lado invertendo
 * o sentido.
 */
export function ListSortSelect({ url, filters, options }: { url: string; filters: ListSorting; options: { value: string; label: string }[] }) {
    function apply(overrides: Partial<ListSorting>) {
        router.get(url, { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <div className="flex items-center gap-2">
            <Select value={filters.sort} onValueChange={(sort) => apply({ sort, direction: 'asc' })}>
                <SelectTrigger className="w-44" aria-label="Ordenar por">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Button
                variant="outline"
                size="icon"
                aria-label={filters.direction === 'asc' ? 'Ordem crescente' : 'Ordem decrescente'}
                title={filters.direction === 'asc' ? 'Crescente' : 'Decrescente'}
                onClick={() => apply({ direction: filters.direction === 'asc' ? 'desc' : 'asc' })}
            >
                {filters.direction === 'asc' ? <ArrowUp /> : <ArrowDown />}
            </Button>
        </div>
    );
}
