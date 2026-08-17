import { MonthFilter, monthLabel } from '@/components/finance/month-filter';
import { SettleDialog } from '@/components/finance/settle-dialog';
import { TransactionFormSheet } from '@/components/finance/transaction-form-sheet';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { MultiSelect } from '@/components/ui/multi-select';
import { SortableHeader, type SortDirection } from '@/components/ui/sortable-header';
import { StatusPicker } from '@/components/ui/status-picker';
import { colorOf, transactionStatusOptions } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { CostCenter, FinanceCategory, FinanceFilters, FinanceSummary, Transaction } from '@/types/finance';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Banknote, CircleAlert, CircleCheck, Clock, Layers, Plus, RefreshCw, Search, Wallet } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Financeiro', href: '/financeiro' },
];

interface FinanceiroPageProps {
    transactions: Paginated<Transaction>;
    filters: FinanceFilters;
    summary: FinanceSummary;
    costCenters: CostCenter[];
    categories: FinanceCategory[];
    clients: { id: number; name: string }[];
    paymentMethods: { id: number; name: string }[];
    suppliers: { id: number; name: string; search?: string }[];
    projected: ProjectedCharge[];
    months: string[];
}

export default function Financeiro({
    transactions,
    filters,
    summary,
    costCenters,
    categories,
    clients,
    paymentMethods,
    suppliers,
    months,
    projected,
}: FinanceiroPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Transaction | null>(null);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => apply({ search }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    function apply(overrides: Partial<FinanceFilters>) {
        router.get(route('financeiro.index'), { ...filters, search, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function open(transaction: Transaction | null) {
        setEditing(transaction);
        setFormOpen(true);
    }

    // Array vazio é falsy só se testado por length — Boolean([]) é true.
    const hasFilters = Boolean(
        filters.search ||
            filters.type.length ||
            filters.status.length ||
            filters.cost_center_id.length ||
            filters.finance_category_id.length ||
            filters.month,
    );

    const sortProps = {
        sort: filters.sort,
        direction: filters.direction,
        onSort: (sort: string, direction: SortDirection) => apply({ sort, direction }),
    };

    /** Os indicadores seguem o mesmo recorte de período da listagem. */
    const period = filters.month ? monthLabel(filters.month) : 'todos os períodos';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Financeiro" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Financeiro</h1>
                        <p className="text-muted-foreground text-sm">Contas a pagar e a receber, separadas por centro de custo.</p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('financeiro.recorrencias.index')}>
                                <RefreshCw />
                                Recorrências
                            </Link>
                        </Button>

                        <Button onClick={() => open(null)}>
                            <Plus />
                            Novo lançamento
                        </Button>
                    </div>
                </div>

                {/*
                  Os seis numa linha só. A direção do dinheiro deixou de ser um
                  título e virou a cor do cartão: os três primeiros saem, os três
                  últimos entram.
                */}
                <div className="space-y-2">
                    <p className="text-muted-foreground text-xs">
                        <span className="text-destructive font-medium">A pagar</span> e <span className="text-success font-medium">a receber</span> ·{' '}
                        {period}
                    </p>

                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        {[
                            {
                                label: 'A pagar',
                                card: summary.payable.total,
                                icon: ArrowDownLeft,
                                status: '',
                                hint: 'Tudo do período',
                                type: 'payable',
                                tone: 'destructive' as const,
                            },
                            {
                                label: 'Paga',
                                card: summary.payable.paid,
                                icon: Banknote,
                                status: 'paid',
                                hint: 'Já baixado',
                                type: 'payable',
                                tone: 'destructive' as const,
                            },
                            {
                                label: 'Atrasada',
                                card: summary.payable.overdue,
                                icon: CircleAlert,
                                status: 'overdue',
                                hint: 'Venceu sem baixa',
                                type: 'payable',
                                tone: 'destructive' as const,
                            },
                            {
                                label: 'A receber',
                                card: summary.receivable.total,
                                icon: ArrowUpRight,
                                status: '',
                                hint: 'Tudo do período',
                                type: 'receivable',
                                tone: 'success' as const,
                            },
                            {
                                label: 'Recebida',
                                card: summary.receivable.paid,
                                icon: CircleCheck,
                                status: 'paid',
                                hint: 'Já baixado',
                                type: 'receivable',
                                tone: 'success' as const,
                            },
                            {
                                label: 'Em aberto',
                                card: summary.receivable.open,
                                icon: Clock,
                                status: 'pending,overdue',
                                hint: 'Ainda não entrou',
                                type: 'receivable',
                                tone: 'success' as const,
                            },
                        ].map((item) => (
                            <Stat
                                key={`${item.type}-${item.label}`}
                                {...item}
                                active={filters.type.join() === item.type && filters.status.join() === item.status}
                                // "Em aberto" vale por duas situações; daí o split.
                                onClick={() => apply({ type: [item.type], status: item.status ? item.status.split(',') : [] })}
                            />
                        ))}
                    </div>
                </div>

                <Card>
                    <div className="flex flex-wrap items-center gap-3 p-4">
                        <div className="min-w-56 flex-1">
                            <Input
                                startIcon={Search}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar descrição ou cliente…"
                            />
                        </div>

                        {/* Todos aceitam mais de uma escolha: dá para pedir
                            "em aberto" e "vencida" juntas. Nada marcado = todos. */}
                        <MultiSelect
                            aria-label="Tipo"
                            className="w-40"
                            value={filters.type}
                            onChange={(value) => apply({ type: value })}
                            options={[
                                { value: 'payable', label: 'A pagar' },
                                { value: 'receivable', label: 'A receber' },
                            ]}
                            allLabel="Todo tipo"
                        />

                        <MultiSelect
                            aria-label="Situação"
                            className="w-44"
                            value={filters.status}
                            onChange={(value) => apply({ status: value })}
                            options={[
                                /*
                                 * Mesmos nomes dos selos da lista: ver "Paga" no
                                 * filtro e "Paga" na linha evita traduzir um para
                                 * o outro de cabeça.
                                 *
                                 * "Em aberto" saiu daqui: era a soma de "a vencer"
                                 * e "vencida", e só existia porque o filtro aceitava
                                 * um valor por vez. Marcando as duas dá o mesmo — e
                                 * uma opção que se sobrepõe às outras faz a pessoa
                                 * parar para pensar qual escolher.
                                 */
                                { value: 'pending', label: 'A vencer' },
                                { value: 'overdue', label: 'Vencida' },
                                { value: 'paid', label: 'Paga' },
                            ]}
                            allLabel="Toda situação"
                        />

                        <MultiSelect
                            aria-label="Centro de custo"
                            className="w-48"
                            value={filters.cost_center_id}
                            onChange={(value) => apply({ cost_center_id: value })}
                            options={costCenters.map((center) => ({ value: String(center.id), label: center.name }))}
                            allLabel="Todos os centros"
                            searchPlaceholder="Buscar centro…"
                            emptyText="Nenhum centro."
                        />

                        <MultiSelect
                            aria-label="Categoria"
                            className="w-48"
                            value={filters.finance_category_id}
                            onChange={(value) => apply({ finance_category_id: value })}
                            options={categories.map((category) => ({ value: String(category.id), label: category.name }))}
                            allLabel="Todas as categorias"
                            searchPlaceholder="Buscar categoria…"
                            emptyText="Nenhuma categoria."
                        />

                        <MonthFilter value={filters.month} options={months} onChange={(month) => apply({ month })} />

                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    apply({
                                        search: '',
                                        type: [],
                                        status: [],
                                        cost_center_id: [],
                                        finance_category_id: [],
                                        month: new Date().toISOString().slice(0, 7),
                                    });
                                }}
                            >
                                Limpar
                            </Button>
                        )}
                    </div>

                    {transactions.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 border-t px-6 py-16 text-center">
                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                <Wallet className="size-5" />
                            </span>
                            <p className="text-sm font-medium">{hasFilters ? 'Nada encontrado' : 'Nenhum lançamento'}</p>
                            <p className="text-muted-foreground text-sm">
                                {hasFilters ? 'Tente outros filtros.' : 'Cadastre a primeira conta a pagar ou receber.'}
                            </p>
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full table-fixed text-sm">
                                    <thead>
                                        <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                            <SortableHeader column="client" label="Cliente" className="w-[17%] pr-4 pl-6" {...sortProps} />
                                            <SortableHeader column="description" label="Descrição" className="w-[20%]" {...sortProps} />
                                            <SortableHeader column="category" label="Categoria" className="w-[13%]" {...sortProps} />
                                            <SortableHeader column="cost_center" label="Centro" className="w-[13%]" {...sortProps} />
                                            <th className="w-[11%] px-4 py-2.5">Tipo</th>
                                            <SortableHeader column="due_date" label="Vencimento" className="w-[11%]" {...sortProps} />
                                            <SortableHeader column="amount" label="Valor" align="right" className="w-[10%]" {...sortProps} />
                                            {/* Encostada na direita: sem sobra depois do seletor. */}
                                            <SortableHeader
                                                column="paid_at"
                                                label="Situação"
                                                align="right"
                                                className="w-[13%] pr-6 pl-4"
                                                {...sortProps}
                                            />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {transactions.data.map((transaction) => (
                                            <Row key={transaction.id} transaction={transaction} onOpen={() => open(transaction)} />
                                        ))}

                                        {/*
                                          Cobranças de recorrência que ainda não viraram
                                          conta. Aparecem esmaecidas e sem ações: são o
                                          que está combinado, não o que já foi lançado.
                                        */}
                                        {projected.map((item) => (
                                            <ProjectedRow key={`${item.recurrence_id}-${item.due_date}`} item={item} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Pagination page={transactions} />
                        </>
                    )}
                </Card>
            </div>

            <TransactionFormSheet
                open={formOpen}
                onOpenChange={(value) => {
                    setFormOpen(value);
                    if (!value) setEditing(null);
                }}
                transaction={editing}
                costCenters={costCenters}
                categories={categories}
                clients={clients}
                paymentMethods={paymentMethods}
                suppliers={suppliers}
            />
        </AppLayout>
    );
}

function Row({ transaction, onOpen }: { transaction: Transaction; onOpen: () => void }) {
    const stop = (event: React.MouseEvent) => event.stopPropagation();
    const isPayable = transaction.type === 'payable';
    const center = colorOf(transaction.cost_center?.color);
    const category = colorOf(transaction.category?.color);
    const [settling, setSettling] = useState(false);

    return (
        <tr onClick={onOpen} className="hover:bg-muted/40 cursor-pointer border-b transition-colors last:border-b-0">
            <td className="py-3 pr-4 pl-6">
                {transaction.client ? (
                    <Link
                        href={route('clientes.show', transaction.client.id)}
                        onClick={stop}
                        className="hover:text-primary block truncate font-medium"
                    >
                        {transaction.client.name}
                    </Link>
                ) : (
                    <span className="text-muted-foreground">{transaction.counterpart ?? '—'}</span>
                )}
            </td>

            <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                    {/* A seta diz a direção do dinheiro sem gastar uma coluna. */}
                    {isPayable ? (
                        <ArrowDownLeft className="text-destructive size-3.5 shrink-0" aria-label="A pagar" />
                    ) : (
                        <ArrowUpRight className="text-success size-3.5 shrink-0" aria-label="A receber" />
                    )}
                    <span className="truncate">{transaction.description}</span>
                </div>

                {/* Fornecedor só quando há cliente na coluna ao lado — senão repetiria. */}
                {transaction.client && transaction.counterpart && (
                    <div className="text-muted-foreground truncate text-xs">{transaction.counterpart}</div>
                )}
            </td>

            <td className="px-4 py-3">
                {transaction.category ? (
                    <Badge variant="outline" className={category.chip}>
                        {transaction.category.name}
                    </Badge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>

            <td className="px-4 py-3">
                {transaction.cost_center ? (
                    <span className="flex items-center gap-2">
                        <span className={cn('size-2 shrink-0 rounded-full', center.dot)} />
                        <span className="truncate">{transaction.cost_center.name}</span>
                    </span>
                ) : (
                    <span className="text-muted-foreground">—</span>
                )}
            </td>

            <td className="px-4 py-3">
                <KindBadge kind={transaction.kind} number={transaction.installment} total={transaction.installments} />
            </td>

            <td className="px-4 py-3">
                <div className={cn('tabular text-sm', transaction.status === 'overdue' ? 'text-destructive font-medium' : 'text-muted-foreground')}>
                    {transaction.due_date_label}
                </div>
                {transaction.paid_at_label && <div className="tabular text-muted-foreground text-xs">pago {transaction.paid_at_label}</div>}
            </td>

            <td className="tabular px-4 py-3 text-right">
                <span className={cn(isPayable ? 'text-destructive' : 'text-success')}>
                    {isPayable ? '−' : '+'} {formatCurrency(transaction.amount)}
                </span>
            </td>

            <td className="py-3 pr-6 pl-4" onClick={stop}>
                {/* O seletor tem largura própria; justify-end é o que o encosta na borda. */}
                <div className="flex justify-end">
                    <StatusPicker
                        value={transaction.status}
                        options={transactionStatusOptions}
                        url={route('financeiro.status', transaction.id)}
                        field="status"
                        label="situação"
                        /*
                         * Dar baixa abre o diálogo: a data em que o dinheiro
                         * andou raramente é a data em que se lança no sistema.
                         * Estornar segue direto — não há o que perguntar.
                         */
                        onIntercept={(next) => {
                            if (next !== 'paid') {
                                return false;
                            }

                            setSettling(true);

                            return true;
                        }}
                    />
                </div>

                <SettleDialog
                    open={settling}
                    onOpenChange={setSettling}
                    url={route('financeiro.status', transaction.id)}
                    description={transaction.description}
                    amount={transaction.amount}
                    isReceivable={!isPayable}
                />
            </td>
        </tr>
    );
}

interface StatCard {
    label: string;
    card: { amount: number; count: number };
    icon: typeof Wallet;
    /** Situação que o card filtra ao ser clicado; vazio = todas. */
    status: string;
    hint: string;
}

function Stat({
    icon: Icon,
    label,
    card,
    hint,
    tone,
    active,
    onClick,
}: StatCard & {
    tone: 'destructive' | 'success';
    active: boolean;
    onClick: () => void;
}) {
    return (
        <Card onClick={onClick} className={cn('h-full cursor-pointer hover:shadow-md', active && 'border-primary ring-primary/20 ring-2')}>
            {/* Compacto: com seis lado a lado, o espaço por cartão caiu pela metade. */}
            <CardContent className="flex h-full flex-col gap-2 p-4">
                <div className="flex items-start justify-between gap-2">
                    <span className="text-muted-foreground truncate text-xs font-medium">{label}</span>
                    <span
                        className={cn(
                            'flex size-7 shrink-0 items-center justify-center rounded-lg',
                            tone === 'destructive' ? 'bg-destructive/10 text-destructive' : 'bg-success/10 text-success',
                        )}
                    >
                        <Icon className="size-4" />
                    </span>
                </div>

                <span className="tabular truncate text-xl font-bold tracking-tight" title={formatCurrency(card.amount)}>
                    {formatCurrency(card.amount)}
                </span>

                <span className="text-muted-foreground mt-auto text-[0.7rem] leading-tight">
                    {hint} · <span className="tabular">{formatNumber(card.count)}</span> {card.count === 1 ? 'conta' : 'contas'}
                </span>
            </CardContent>
        </Card>
    );
}

/** Cobrança que a recorrência ainda vai gerar — projeção, não lançamento. */
export interface ProjectedCharge {
    recurrence_id: number;
    type: 'payable' | 'receivable';
    description: string;
    amount: number;
    due_date: string;
    due_date_label: string;
    client: string | null;
    cost_center: { name: string; color: string } | null;
    category: string | null;
}

function ProjectedRow({ item }: { item: ProjectedCharge }) {
    return (
        <tr className="text-muted-foreground border-b border-dashed last:border-b-0">
            <td className="truncate py-3 pr-4 pl-6">{item.client ?? '—'}</td>

            <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                    {item.type === 'payable' ? (
                        <ArrowDownLeft className="text-destructive/60 size-3.5 shrink-0" aria-label="A pagar" />
                    ) : (
                        <ArrowUpRight className="text-success/60 size-3.5 shrink-0" aria-label="A receber" />
                    )}
                    <span className="truncate">{item.description}</span>
                </div>
            </td>

            <td className="px-4 py-3">{item.category ?? '—'}</td>

            <td className="px-4 py-3">
                {item.cost_center && (
                    <span className={cn('rounded border px-1.5 text-[0.7rem]', colorOf(item.cost_center.color).chip)}>{item.cost_center.name}</span>
                )}
            </td>

            <td className="px-4 py-3">
                {/* Mesmo tamanho da linha real, para a coluna não ter dois pesos. */}
                <span className="flex items-center gap-1.5">
                    <RefreshCw className="size-4 shrink-0" />
                    Recorrente
                </span>
            </td>

            <td className="tabular px-4 py-3 text-sm">{item.due_date_label}</td>

            <td className="tabular px-4 py-3 text-right">{formatCurrency(item.amount)}</td>

            <td className="py-3 pr-6 pl-4 text-right">
                <Badge variant="muted">Prevista</Badge>
            </td>
        </tr>
    );
}

/**
 * Como a conta nasceu, e em que posição da série ela está.
 *
 * Avulsa não ganha selo: a ausência já diz o que é, e um selo em toda linha só
 * ocuparia a coluna sem informar.
 */
function KindBadge({ kind, number, total }: { kind: Transaction['kind']; number: number | null; total: number | null }) {
    // Sem tamanho próprio: herda o da tabela, igual à descrição ao lado.
    if (kind === 'once') {
        return <span className="text-muted-foreground">Única</span>;
    }

    const isRecurring = kind === 'recurring';
    const position = number ? `${String(number).padStart(2, '0')}/${total ? String(total).padStart(2, '0') : '—'}` : null;

    return (
        <span className="flex items-center gap-1.5">
            {isRecurring ? <RefreshCw className="text-primary size-4 shrink-0" /> : <Layers className="text-muted-foreground size-4 shrink-0" />}
            <span className="truncate">
                {isRecurring ? 'Recorrente' : 'Parcelada'}
                {position && <span className="tabular text-muted-foreground ml-1">{position}</span>}
            </span>
        </span>
    );
}
