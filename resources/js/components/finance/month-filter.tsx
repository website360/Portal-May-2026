import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ChevronLeft, ChevronRight } from 'lucide-react';

/** "2026-08" → "Agosto 2026". */
export function monthLabel(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const name = new Date(year, monthNumber - 1, 1).toLocaleDateString('pt-BR', { month: 'long' });

    return `${name.charAt(0).toUpperCase()}${name.slice(1)} ${year}`;
}

/** "2026-08" → "ago/2026", para caber no rodapé dos indicadores. */
export function shortMonthLabel(month: string): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const name = new Date(year, monthNumber - 1, 1).toLocaleDateString('pt-BR', { month: 'short' });

    return `${name.replace('.', '')}/${year}`;
}

const ALL = '__all__';

function shift(month: string, months: number): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const date = new Date(year, monthNumber - 1 + months, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

interface MonthFilterProps {
    value: string;
    onChange: (month: string) => void;
    /** Meses que têm lançamento; o corrente entra sempre. */
    options: string[];
}

/**
 * O `input type="month"` nativo aparece como "--------- de ----" e não diz o que
 * é. Aqui o mês é sempre legível, com setas para andar e a opção de ver tudo.
 */
export function MonthFilter({ value, onChange, options }: MonthFilterProps) {
    const showing = value || options[0];

    return (
        <div className="flex items-center gap-1">
            <Button variant="outline" size="icon" aria-label="Mês anterior" disabled={!value} onClick={() => onChange(shift(showing, -1))}>
                <ChevronLeft />
            </Button>

            <Select value={value || ALL} onValueChange={(next) => onChange(next === ALL ? '' : next)}>
                <SelectTrigger className="w-44" aria-label="Mês do vencimento">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos os períodos</SelectItem>
                    {options.map((month) => (
                        <SelectItem key={month} value={month}>
                            {monthLabel(month)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Button variant="outline" size="icon" aria-label="Próximo mês" disabled={!value} onClick={() => onChange(shift(showing, 1))}>
                <ChevronRight />
            </Button>
        </div>
    );
}
