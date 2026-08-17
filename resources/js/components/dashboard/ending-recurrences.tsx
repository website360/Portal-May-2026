import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { ArrowRight, RefreshCw } from 'lucide-react';

export interface EndingRecurrence {
    id: number;
    description: string;
    client: string | null;
    amount: number;
    next_due_at: string;
    remaining: number | null;
    is_last: boolean;
    type: 'payable' | 'receivable';
}

const date = (value: string) => new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR');

/**
 * Contratos chegando ao fim.
 *
 * Some quando não há nenhum: um cartão vazio dizendo "nada acabando" ocuparia
 * atenção todo dia para não informar nada.
 */
export function EndingRecurrences({ recurrences }: { recurrences: EndingRecurrence[] }) {
    if (recurrences.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <RefreshCw className="text-warning size-4" />
                    Contratos acabando
                </CardTitle>

                <Button variant="ghost" size="sm" asChild>
                    <Link href={route('financeiro.recorrencias.index')}>
                        Ver todos
                        <ArrowRight />
                    </Link>
                </Button>
            </CardHeader>

            <CardContent className="p-0">
                <ul className="border-t">
                    {recurrences.map((item) => (
                        <li key={item.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b px-6 py-3 last:border-b-0">
                            <div className="min-w-40 flex-1">
                                <p className="truncate text-sm font-medium">{item.description}</p>
                                <p className="text-muted-foreground truncate text-xs">
                                    {item.client ?? (item.type === 'payable' ? 'Despesa da agência' : 'Sem cliente')} · próxima em{' '}
                                    <span className="tabular">{date(item.next_due_at)}</span>
                                </p>
                            </div>

                            <span className="tabular text-sm">{formatCurrency(item.amount)}</span>

                            <Badge variant={item.is_last ? 'destructive' : 'warning'}>
                                {item.is_last ? 'próxima é a última' : `faltam ${item.remaining}`}
                            </Badge>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
