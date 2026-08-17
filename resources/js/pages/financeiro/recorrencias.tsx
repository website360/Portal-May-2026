import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SortableHeader, type SortDirection } from '@/components/ui/sortable-header';
import { colorOf } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, CircleAlert, RefreshCw, Trash2, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Financeiro', href: '/financeiro' },
    { title: 'Recorrências', href: '/financeiro/recorrencias' },
];

const INTERVALS: Record<string, string> = {
    monthly: 'Mensal',
    quarterly: 'Trimestral',
    semiannual: 'Semestral',
    annual: 'Anual',
};

export interface Recurrence {
    id: number;
    type: 'payable' | 'receivable';
    description: string;
    amount: number;
    interval: string;
    next_due_at: string;
    ends_at: string | null;
    active: boolean;
    running: boolean;
    /** Null quando não há data de fim — contrato sem encerramento previsto. */
    remaining: number | null;
    is_last: boolean;
    is_ending: boolean;
    has_ended: boolean;
    client: { id: number; name: string } | null;
    cost_center: { id: number; name: string; color: string } | null;
    category: { id: number; name: string; color: string } | null;
}

interface PageProps {
    recurrences: Recurrence[];
    stats: { total: number; running: number; ending: number };
    filters: { sort: string; direction: SortDirection };
}

const date = (value: string) => new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR');

export default function Recorrencias({ recurrences, stats, filters }: PageProps) {
    const [renewing, setRenewing] = useState<Recurrence | null>(null);
    const [deleting, setDeleting] = useState<Recurrence | null>(null);

    const sortProps = {
        sort: filters.sort,
        direction: filters.direction,
        onSort: (sort: string, direction: SortDirection) =>
            router.get(route('financeiro.recorrencias.index'), { sort, direction }, { preserveState: true, preserveScroll: true, replace: true }),
    };

    const ending = recurrences.filter((r) => r.is_ending);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Recorrências" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Recorrências</h1>
                        <p className="text-muted-foreground text-sm">
                            Compromissos que se renovam. Diferente de parcelamento: aqui só a próxima cobrança vira conta.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={route('financeiro.index')}>
                            <Wallet />
                            Ver lançamentos
                        </Link>
                    </Button>
                </div>

                {/* O aviso que motiva a tela: quem está para acabar, em destaque. */}
                {ending.length > 0 && (
                    <Card className="border-warning/40 bg-warning/5">
                        <CardContent className="flex flex-wrap items-start gap-3 p-4">
                            <CircleAlert className="text-warning mt-0.5 size-5 shrink-0" />

                            <div className="min-w-0 flex-1 space-y-2">
                                <p className="text-sm font-medium">
                                    {ending.length === 1 ? 'Um contrato está acabando' : `${ending.length} contratos estão acabando`}
                                </p>

                                <ul className="space-y-1">
                                    {ending.map((r) => (
                                        <li key={r.id} className="text-muted-foreground flex flex-wrap items-center gap-2 text-sm">
                                            <span className="font-medium">{r.description}</span>
                                            {r.client && <span>· {r.client.name}</span>}
                                            <Badge variant={r.is_last ? 'destructive' : 'warning'}>
                                                {r.is_last ? 'próxima é a última' : `faltam ${r.remaining}`}
                                            </Badge>
                                            <span className="tabular">vence {date(r.next_due_at)}</span>
                                            <Button size="sm" variant="outline" onClick={() => setRenewing(r)}>
                                                <RefreshCw />
                                                Renovar
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <p className="text-muted-foreground text-sm">
                    <span className="tabular">{formatNumber(stats.total)}</span> cadastradas ·{' '}
                    <span className="tabular">{formatNumber(stats.running)}</span> ativas ·{' '}
                    <span className="tabular">{formatNumber(stats.ending)}</span> acabando
                </p>

                <Card>
                    {recurrences.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 px-6 py-16 text-center">
                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                <RefreshCw className="size-5" />
                            </span>
                            <p className="text-sm font-medium">Nenhuma recorrência ainda</p>
                            <p className="text-muted-foreground max-w-md text-sm">
                                Crie uma pelo formulário de lançamento: escolha <strong>Recorrente</strong> em Repetição.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                        <SortableHeader column="description" label="Compromisso" className="pr-4 pl-6" {...sortProps} />
                                        <SortableHeader column="client" label="Cliente" {...sortProps} />
                                        <th className="px-4 py-2.5">Repete</th>
                                        <SortableHeader column="next_due_at" label="Próxima" {...sortProps} />
                                        <th className="px-4 py-2.5">Restam</th>
                                        <SortableHeader column="amount" label="Valor" align="right" {...sortProps} />
                                        <th className="w-28 py-2.5 pr-6 pl-4" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {recurrences.map((r) => (
                                        <tr key={r.id} className="hover:bg-muted/40 border-b transition-colors last:border-b-0">
                                            <td className="py-3 pr-4 pl-6">
                                                <div className="flex items-center gap-2">
                                                    {r.type === 'payable' ? (
                                                        <ArrowDownLeft className="text-destructive size-3.5 shrink-0" aria-label="A pagar" />
                                                    ) : (
                                                        <ArrowUpRight className="text-success size-3.5 shrink-0" aria-label="A receber" />
                                                    )}
                                                    <span className="font-medium">{r.description}</span>
                                                    {!r.active && <Badge variant="muted">Parada</Badge>}
                                                    {r.has_ended && <Badge variant="muted">Encerrada</Badge>}
                                                </div>
                                                {r.cost_center && (
                                                    <span
                                                        className={cn(
                                                            'mt-0.5 inline-block rounded border px-1.5 text-[0.7rem]',
                                                            colorOf(r.cost_center.color).chip,
                                                        )}
                                                    >
                                                        {r.cost_center.name}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">{r.client?.name ?? '—'}</td>
                                            <td className="text-muted-foreground px-4 py-3">{INTERVALS[r.interval] ?? r.interval}</td>
                                            <td className="tabular px-4 py-3">{date(r.next_due_at)}</td>
                                            <td className="px-4 py-3">
                                                {r.remaining === null ? (
                                                    <span className="text-muted-foreground text-xs">sem fim</span>
                                                ) : r.is_last ? (
                                                    <Badge variant="destructive">última</Badge>
                                                ) : r.is_ending ? (
                                                    <Badge variant="warning">{r.remaining}</Badge>
                                                ) : (
                                                    <span className="tabular">{r.remaining}</span>
                                                )}
                                            </td>
                                            <td className="tabular px-4 py-3 text-right">{formatCurrency(r.amount)}</td>
                                            <td className="py-3 pr-6 pl-4 text-right">
                                                <div className="flex items-center justify-end gap-0.5">
                                                    <Button variant="ghost" size="icon-sm" onClick={() => setRenewing(r)} title="Renovar">
                                                        <RefreshCw />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => setDeleting(r)}
                                                        title="Encerrar"
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>

            <RenewDialog recurrence={renewing} onClose={() => setRenewing(null)} />

            <Dialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Encerrar {deleting?.description}?</DialogTitle>
                        <DialogDescription>
                            Para de gerar novas cobranças. As que já foram emitidas continuam no financeiro, intactas.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="secondary" onClick={() => setDeleting(null)}>
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete(route('financeiro.recorrencias.destroy', deleting!.id), {
                                    preserveScroll: true,
                                    onFinish: () => setDeleting(null),
                                })
                            }
                        >
                            Encerrar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function RenewDialog({ recurrence, onClose }: { recurrence: Recurrence | null; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ cycles: '12', amount: '' });

    // Abre já com o valor atual: renovar é a hora natural de reajustar.
    useEffect(() => {
        if (recurrence) {
            setData({ cycles: recurrence.interval === 'annual' ? '1' : '12', amount: String(recurrence.amount) });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [recurrence?.id]);

    const unit = recurrence?.interval === 'annual' ? 'ano' : recurrence?.interval === 'monthly' ? 'mês' : 'ciclo';

    return (
        <Dialog open={recurrence !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Renovar {recurrence?.description}</DialogTitle>
                    <DialogDescription>
                        Estende o contrato. O próximo vencimento não muda, e as cobranças já emitidas continuam com o valor antigo.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="renew"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(route('financeiro.recorrencias.renovar', recurrence!.id), {
                            preserveScroll: true,
                            onSuccess: () => {
                                reset();
                                onClose();
                            },
                        });
                    }}
                    className="grid gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="cycles">Por mais quantos {unit === 'ano' ? 'anos' : unit === 'mês' ? 'meses' : 'ciclos'}?</Label>
                        <Input
                            id="cycles"
                            type="number"
                            min="1"
                            max="120"
                            className="w-28"
                            value={data.cycles}
                            onChange={(e) => setData('cycles', e.target.value)}
                        />
                        {errors.cycles && <p className="text-destructive text-xs font-medium">{errors.cycles}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="renew-amount">Valor a partir da próxima cobrança</Label>
                        <div className="max-w-48">
                            <CurrencyInput id="renew-amount" value={data.amount} onChange={(value) => setData('amount', value)} />
                        </div>
                        {errors.amount && <p className="text-destructive text-xs font-medium">{errors.amount}</p>}
                    </div>
                </form>

                <DialogFooter>
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" form="renew" loading={processing}>
                        Renovar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
