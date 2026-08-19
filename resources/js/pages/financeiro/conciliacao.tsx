import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronLeft, ChevronRight, ExternalLink, Plug } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Financeiro', href: '/financeiro' },
    { title: 'Conciliação Asaas', href: '/financeiro/conciliacao' },
];

interface Matched {
    id: number;
    client: string | null;
    description: string;
    amount: number;
    due_date_label: string;
    system_paid: boolean;
    asaas_id: string;
    asaas_status: string | null;
    asaas_paid: boolean;
    invoice_number: string | null;
    invoice_url: string | null;
    linked: boolean;
}
interface SystemOnly {
    id: number;
    client: string | null;
    description: string;
    amount: number;
    due_date_label: string;
}
interface AsaasOnly {
    asaas_id: string;
    doc: string;
    client_name: string;
    description: string;
    amount: number;
    due_date: string;
    due_date_label: string;
    status: string | null;
    paid: boolean;
    invoice_number: string | null;
    invoice_url: string | null;
}

interface PageProps {
    configured: boolean;
    month: string;
    matched: Matched[];
    systemOnly: SystemOnly[];
    asaasOnly: AsaasOnly[];
    error: string | null;
}

function shiftMonth(month: string, delta: number): string {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}
function monthLabel(month: string): string {
    const [y, m] = month.split('-').map(Number);
    return new Date(y, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
}
function StatusBadge({ status, paid }: { status: string | null; paid: boolean }) {
    if (paid) return <Badge variant="success">Pago</Badge>;
    if (status === 'OVERDUE') return <Badge variant="destructive">Vencido</Badge>;
    if (status === 'PENDING') return <Badge variant="warning">Pendente</Badge>;
    return <Badge variant="muted">{status ?? '—'}</Badge>;
}
function InvoiceLink({ url, number }: { url: string | null; number: string | null }) {
    if (!url) return <span className="text-muted-foreground text-xs">{number ?? '—'}</span>;
    return (
        <a href={url} target="_blank" rel="noopener noreferrer" className="text-primary inline-flex items-center gap-1 text-xs hover:underline">
            {number ?? 'fatura'}
            <ExternalLink className="size-3" />
        </a>
    );
}

export default function Conciliacao({ configured, month, matched, systemOnly, asaasOnly, error }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Conciliação Asaas" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Conciliação com Asaas</h1>
                        <p className="text-muted-foreground text-sm">Casadas por CPF/CNPJ + valor + vencimento. Confira o que está pago e o que está solto em cada lado.</p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/financeiro">
                            <ArrowLeft />
                            Voltar ao financeiro
                        </Link>
                    </Button>
                </div>

                {!configured ? (
                    <Card>
                        <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                <Plug className="size-5" />
                            </span>
                            <p className="text-sm font-medium">Asaas não configurado</p>
                            <Button variant="outline" asChild>
                                <Link href="/configuracoes/asaas">Configurar Asaas</Link>
                            </Button>
                        </div>
                    </Card>
                ) : (
                    <>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="icon-sm" asChild>
                                <Link href={`/financeiro/conciliacao?month=${shiftMonth(month, -1)}`}>
                                    <ChevronLeft />
                                </Link>
                            </Button>
                            <span className="text-sm font-medium capitalize">{monthLabel(month)}</span>
                            <Button variant="outline" size="icon-sm" asChild>
                                <Link href={`/financeiro/conciliacao?month=${shiftMonth(month, 1)}`}>
                                    <ChevronRight />
                                </Link>
                            </Button>
                        </div>

                        {error && <p className="text-destructive text-sm">{error}</p>}

                        <MatchedSection items={matched} />

                        <SystemOnlySection items={systemOnly} />
                        <AsaasOnlySection items={asaasOnly} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function SystemOnlySection({ items }: { items: SystemOnly[] }) {
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<number[] | null>(null);

    const toggle = (id: number) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

    function create(ids: number[]) {
        router.post('/financeiro/conciliacao/criar-no-asaas', { ids }, {
            preserveScroll: true,
            onFinish: () => {
                setPending(null);
                setSelected([]);
            },
        });
    }

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                <div className="space-y-1">
                    <CardTitle>Só no sistema</CardTitle>
                    <CardDescription>Sem cobrança correspondente no Asaas. {items.length} no mês.</CardDescription>
                </div>
                {selected.length > 0 && (
                    <Button size="sm" onClick={() => setPending(selected)}>
                        Criar no Asaas ({selected.length})
                    </Button>
                )}
            </CardHeader>

            {items.length > 0 && (
                <div className="overflow-x-auto border-t">
                    <table className="w-full text-sm">
                        <tbody>
                            {items.map((s) => (
                                <tr key={s.id} className="border-b transition-colors last:border-b-0">
                                    <td className="py-3 pr-2 pl-6">
                                        <Checkbox checked={selected.includes(s.id)} onCheckedChange={() => toggle(s.id)} />
                                    </td>
                                    <td className="py-3 pr-4 pl-2">
                                        <div className="font-medium">{s.client ?? '—'}</div>
                                        <div className="text-muted-foreground text-xs">{s.description} · venc. {s.due_date_label}</div>
                                    </td>
                                    <td className="tabular px-4 py-3 text-right">{formatCurrency(s.amount)}</td>
                                    <td className="py-3 pr-6 pl-4 text-right">
                                        <Button size="sm" variant="outline" onClick={() => setPending([s.id])}>
                                            Criar no Asaas
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={() => setPending(null)}
                title={`Criar ${pending?.length ?? 0} cobrança(s) no Asaas?`}
                description="Cria a(s) cobrança(s) no Asaas (o cliente é criado lá se ainda não existir) e vincula à conciliação."
                confirmLabel="Criar no Asaas"
                onConfirm={() => pending && create(pending)}
            />
        </Card>
    );
}

function AsaasOnlySection({ items }: { items: AsaasOnly[] }) {
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const [pending, setPending] = useState<AsaasOnly[] | null>(null);

    const toggle = (id: string) => setSelectedIds((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

    function create(list: AsaasOnly[]) {
        const payload = list.map((a) => ({
            asaas_id: a.asaas_id,
            doc: a.doc,
            client_name: a.client_name,
            description: a.description,
            amount: a.amount,
            due_date: a.due_date,
            invoice_number: a.invoice_number,
            invoice_url: a.invoice_url,
        }));
        router.post('/financeiro/conciliacao/criar-lancamento', { items: payload }, {
            preserveScroll: true,
            onFinish: () => {
                setPending(null);
                setSelectedIds([]);
            },
        });
    }

    const selectedItems = items.filter((a) => selectedIds.includes(a.asaas_id));

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                <div className="space-y-1">
                    <CardTitle>Só no Asaas</CardTitle>
                    <CardDescription>Sem lançamento correspondente no sistema. {items.length} no mês.</CardDescription>
                </div>
                {selectedItems.length > 0 && (
                    <Button size="sm" onClick={() => setPending(selectedItems)}>
                        Criar lançamento ({selectedItems.length})
                    </Button>
                )}
            </CardHeader>

            {items.length > 0 && (
                <div className="overflow-x-auto border-t">
                    <table className="w-full text-sm">
                        <tbody>
                            {items.map((a) => (
                                <tr key={a.asaas_id} className="border-b transition-colors last:border-b-0">
                                    <td className="py-3 pr-2 pl-6">
                                        <Checkbox checked={selectedIds.includes(a.asaas_id)} onCheckedChange={() => toggle(a.asaas_id)} />
                                    </td>
                                    <td className="py-3 pr-4 pl-2">
                                        <div className="font-medium">{a.client_name || `Doc ${a.doc || '—'}`}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {a.description || 'Cobrança Asaas'} · venc. {a.due_date_label}
                                        </div>
                                    </td>
                                    <td className="tabular px-4 py-3 text-right">{formatCurrency(a.amount)}</td>
                                    <td className="px-4 py-3"><InvoiceLink url={a.invoice_url} number={a.invoice_number} /></td>
                                    <td className="px-4 py-3"><StatusBadge status={a.status} paid={a.paid} /></td>
                                    <td className="py-3 pr-6 pl-4 text-right">
                                        <Button size="sm" variant="outline" onClick={() => setPending([a])}>
                                            Criar lançamento
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={() => setPending(null)}
                title={`Criar ${pending?.length ?? 0} lançamento(s) no sistema?`}
                description="Cria conta(s) a receber a partir da(s) cobrança(s) do Asaas, casando o cliente pelo CPF/CNPJ quando existir."
                confirmLabel="Criar lançamento"
                onConfirm={() => pending && create(pending)}
            />
        </Card>
    );
}

function MatchedSection({ items }: { items: Matched[] }) {
    const [selected, setSelected] = useState<number[]>([]);
    const toggle = (id: number) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

    function conciliar(list: Matched[]) {
        const payload = list.map((m) => ({
            transaction_id: m.id,
            asaas_id: m.asaas_id,
            invoice_number: m.invoice_number,
            invoice_url: m.invoice_url,
        }));
        router.post('/financeiro/conciliacao/conciliar', { items: payload }, {
            preserveScroll: true,
            onFinish: () => setSelected([]),
        });
    }

    const selectedItems = items.filter((m) => !m.linked && selected.includes(m.id));

    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 space-y-0">
                <div className="space-y-1">
                    <CardTitle>Conciliadas</CardTitle>
                    <CardDescription>Encontradas nos dois lados. {items.length} no mês — confirme para conciliar.</CardDescription>
                </div>
                {selectedItems.length > 0 && (
                    <Button size="sm" onClick={() => conciliar(selectedItems)}>
                        Conciliar ({selectedItems.length})
                    </Button>
                )}
            </CardHeader>

            {items.length > 0 && (
                <div className="overflow-x-auto border-t">
                    <table className="w-full text-sm">
                        <tbody>
                            {items.map((m) => (
                                <tr key={m.id} className="border-b transition-colors last:border-b-0">
                                    <td className="py-3 pr-2 pl-6">
                                        {m.linked ? (
                                            <Check className="text-success size-4" />
                                        ) : (
                                            <Checkbox checked={selected.includes(m.id)} onCheckedChange={() => toggle(m.id)} />
                                        )}
                                    </td>
                                    <td className="py-3 pr-4 pl-2">
                                        <div className="font-medium">{m.client ?? '—'}</div>
                                        <div className="text-muted-foreground text-xs">{m.description} · venc. {m.due_date_label}</div>
                                    </td>
                                    <td className="tabular px-4 py-3 text-right">{formatCurrency(m.amount)}</td>
                                    <td className="px-4 py-3">
                                        {m.linked ? <InvoiceLink url={m.invoice_url} number={m.invoice_number} /> : <span className="text-muted-foreground text-xs">—</span>}
                                    </td>
                                    <td className="px-4 py-3"><StatusBadge status={m.asaas_status} paid={m.asaas_paid} /></td>
                                    <td className="py-3 pr-6 pl-4 text-right">
                                        {!m.linked ? (
                                            <Button size="sm" variant="outline" onClick={() => conciliar([m])}>
                                                Conciliar
                                            </Button>
                                        ) : m.asaas_paid && !m.system_paid ? (
                                            <Button size="sm" onClick={() => router.post(`/financeiro/conciliacao/${m.id}/baixa`, {}, { preserveScroll: true })}>
                                                Dar baixa
                                            </Button>
                                        ) : m.system_paid ? (
                                            <span className="text-success text-xs font-medium">Baixado</span>
                                        ) : (
                                            <Badge variant="secondary">Conciliada</Badge>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}
