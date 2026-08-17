import { ClientAvatar } from '@/components/clients/client-avatar';
import { MonthFilter } from '@/components/finance/month-filter';
import { MaintenanceDialog } from '@/components/maintenance/maintenance-dialog';
import { MaintenanceStatusBadge } from '@/components/maintenance/maintenance-status-badge';
import { PlanFormSheet } from '@/components/maintenance/plan-form-sheet';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { MultiSelect } from '@/components/ui/multi-select';
import { SortableHeader, type SortDirection } from '@/components/ui/sortable-header';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { ClientOption } from '@/types/domains';
import {
    HISTORY_FILTER_KEYS,
    PLAN_FILTER_KEYS,
    type Checklist,
    type MaintenanceFilters,
    type MaintenancePlan,
    type MaintenanceRecord,
    type MaintenanceStats,
    type MaintenanceTab,
} from '@/types/maintenance';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarCheck,
    CalendarClock,
    CircleCheck,
    ClipboardCheck,
    MessageCircle,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Send,
    Trash2,
    Wrench,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Manutenção', href: '/manutencao' },
];

const statusOptions = [
    { value: 'late', label: 'Atrasada' },
    { value: 'pending', label: 'Pendente este mês' },
    { value: 'done', label: 'Feita este mês' },
    { value: 'paused', label: 'Pausado' },
];

const reportOptions = [
    { value: 'not_sent', label: 'Não enviado' },
    { value: 'sent', label: 'Enviado' },
];

interface ManutencaoPageProps {
    filters: MaintenanceFilters;
    stats: MaintenanceStats;
    plans: Paginated<MaintenancePlan> | null;
    history: Paginated<MaintenanceRecord> | null;
    clients: ClientOption[];
    checklist: Checklist;
    /** Quem já registrou manutenção — só eles entram no filtro. */
    executors: { value: string; label: string }[];
    /** Meses com manutenção registrada, do mais recente ao mais antigo. */
    months: string[];
}

export default function Manutencao({ filters, stats, plans, history, clients, checklist, executors, months }: ManutencaoPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [planForm, setPlanForm] = useState(false);
    const [editingPlan, setEditingPlan] = useState<MaintenancePlan | null>(null);
    const [running, setRunning] = useState<MaintenancePlan | null>(null);
    const [editingRecord, setEditingRecord] = useState<MaintenanceRecord | null>(null);
    const [deletingPlan, setDeletingPlan] = useState<MaintenancePlan | null>(null);
    const [deletingRecord, setDeletingRecord] = useState<MaintenanceRecord | null>(null);
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

    function apply(overrides: Partial<MaintenanceFilters>) {
        router.get(
            route('manutencao.index'),
            {
                tab: filters.tab,
                search,
                statuses: filters.statuses,
                clients: filters.clients,
                users: filters.users,
                reports: filters.reports,
                month: filters.month,
                sort: filters.sort,
                direction: filters.direction,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    /**
     * Trocar de aba zera ordenação e filtros: as colunas são outras, e um
     * recorte de "atrasadas" não quer dizer nada no histórico. A busca fica,
     * porque procurar por um cliente vale nas duas.
     */
    function goToTab(tab: MaintenanceTab) {
        if (tab === filters.tab) return;

        router.get(route('manutencao.index'), { tab, search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    /** Limpa só os filtros da aba aberta, mantendo o que a outra guarda. */
    function clearFilters() {
        const keys = filters.tab === 'planos' ? PLAN_FILTER_KEYS : HISTORY_FILTER_KEYS;
        const cleared = Object.fromEntries(keys.map((key) => [key, key === 'month' ? '' : []]));

        setSearch('');
        apply({ ...cleared, search: '' } as Partial<MaintenanceFilters>);
    }

    const sortProps = {
        sort: filters.sort,
        direction: filters.direction,
        onSort: (sort: string, direction: SortDirection) => apply({ sort, direction }),
    };

    function openCreate() {
        setEditingPlan(null);
        setPlanForm(true);
    }

    function openEditPlan(plan: MaintenancePlan) {
        setEditingPlan(plan);
        setPlanForm(true);
    }

    const activeKeys = filters.tab === 'planos' ? PLAN_FILTER_KEYS : HISTORY_FILTER_KEYS;

    const hasFilters =
        Boolean(filters.search) || activeKeys.some((key) => (key === 'month' ? Boolean(filters.month) : (filters[key] as string[]).length > 0));

    const clientOptions = clients.map((client) => ({ value: String(client.id), label: client.name }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manutenção" />

            {/* min-w-0: sem isso a tabela larga estica o flex e empurra os cards para fora da tela. */}
            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Manutenção preventiva</h1>
                        <p className="text-muted-foreground text-sm">Os sites que a agência revisa e o que já foi feito em cada um.</p>
                    </div>

                    <Button onClick={openCreate}>
                        <Plus />
                        Novo plano
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat icon={Wrench} label="Planos ativos" value={formatNumber(stats.active)} hint="Sites sob manutenção" />
                    <Stat
                        icon={AlertTriangle}
                        label="Atrasadas"
                        value={formatNumber(stats.late)}
                        hint="Passaram um mês em branco"
                        tone={stats.late > 0 ? 'destructive' : undefined}
                        active={filters.statuses.includes('late')}
                        onClick={() => apply({ tab: 'planos', statuses: filters.statuses.includes('late') ? [] : ['late'] })}
                    />
                    <Stat
                        icon={CalendarClock}
                        label="Pendentes"
                        value={formatNumber(stats.pending)}
                        hint="Faltam neste mês"
                        tone={stats.pending > 0 ? 'warning' : undefined}
                        active={filters.statuses.includes('pending')}
                        onClick={() => apply({ tab: 'planos', statuses: filters.statuses.includes('pending') ? [] : ['pending'] })}
                    />
                    <Stat
                        icon={CalendarCheck}
                        label="Feitas no mês"
                        value={formatNumber(stats.done)}
                        hint="Já revisados"
                        active={filters.statuses.includes('done')}
                        onClick={() => apply({ tab: 'planos', statuses: filters.statuses.includes('done') ? [] : ['done'] })}
                    />
                </div>

                <Card>
                    <div className="flex gap-1 border-b px-4 pt-2">
                        <Tab active={filters.tab === 'planos'} onClick={() => goToTab('planos')} label="Planos" />
                        <Tab active={filters.tab === 'historico'} onClick={() => goToTab('historico')} label="Histórico" />
                    </div>

                    <div className="flex flex-wrap items-center gap-2 p-4">
                        <div className="min-w-56 flex-1">
                            <Input
                                startIcon={Search}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por cliente ou site…"
                            />
                        </div>

                        {filters.tab === 'planos' ? (
                            <MultiSelect
                                value={filters.statuses}
                                onChange={(statuses) => apply({ statuses })}
                                options={statusOptions}
                                allLabel="Qualquer situação"
                                aria-label="Situação"
                                className="w-48"
                            />
                        ) : (
                            <>
                                <MonthFilter value={filters.month} onChange={(month) => apply({ month })} options={months} />

                                <MultiSelect
                                    value={filters.reports}
                                    onChange={(reports) => apply({ reports })}
                                    options={reportOptions}
                                    allLabel="Qualquer relatório"
                                    aria-label="Relatório"
                                    className="w-44"
                                />

                                {/* Só aparece com mais de uma pessoa: com uma, o filtro não recorta nada. */}
                                {executors.length > 1 && (
                                    <MultiSelect
                                        value={filters.users}
                                        onChange={(users) => apply({ users })}
                                        options={executors}
                                        allLabel="Qualquer pessoa"
                                        searchPlaceholder="Buscar pessoa…"
                                        aria-label="Executou"
                                        className="w-44"
                                    />
                                )}
                            </>
                        )}

                        <MultiSelect
                            value={filters.clients}
                            onChange={(selected) => apply({ clients: selected })}
                            options={clientOptions}
                            allLabel="Qualquer cliente"
                            searchPlaceholder="Buscar cliente…"
                            emptyText="Nenhum cliente com esse nome."
                            aria-label="Cliente"
                            className="w-48"
                        />

                        {hasFilters && (
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <X />
                                Limpar
                            </Button>
                        )}
                    </div>

                    {filters.tab === 'planos' ? (
                        <PlanTable
                            plans={plans}
                            sortProps={sortProps}
                            hasFilters={hasFilters}
                            onCreate={openCreate}
                            onRun={setRunning}
                            onEdit={openEditPlan}
                            onDelete={setDeletingPlan}
                        />
                    ) : (
                        <HistoryTable
                            history={history}
                            sortProps={sortProps}
                            hasFilters={hasFilters}
                            onEdit={setEditingRecord}
                            onDelete={setDeletingRecord}
                        />
                    )}
                </Card>
            </div>

            <PlanFormSheet open={planForm} onOpenChange={setPlanForm} plan={editingPlan} clients={clients} />

            <MaintenanceDialog
                open={running !== null || editingRecord !== null}
                onOpenChange={(open) => {
                    if (open) return;
                    setRunning(null);
                    setEditingRecord(null);
                }}
                plan={running}
                record={editingRecord}
                checklist={checklist}
            />

            <ConfirmDialog
                open={deletingPlan !== null}
                onOpenChange={() => setDeletingPlan(null)}
                title={`Excluir o plano de ${deletingPlan?.site_url}?`}
                description="Todo o histórico de manutenções deste site vai junto. Para só parar os avisos, edite o plano e desmarque “ativo”."
                onConfirm={() => {
                    if (!deletingPlan) return;

                    router.delete(route('manutencao.planos.destroy', deletingPlan.id), {
                        preserveScroll: true,
                        onFinish: () => setDeletingPlan(null),
                    });
                }}
            />

            <ConfirmDialog
                open={deletingRecord !== null}
                onOpenChange={() => setDeletingRecord(null)}
                title="Excluir esta manutenção do histórico?"
                description="A data da próxima manutenção volta a ser calculada pela anterior. O relatório já enviado ao cliente não é desfeito."
                onConfirm={() => {
                    if (!deletingRecord) return;

                    router.delete(route('manutencao.registros.destroy', deletingRecord.id), {
                        preserveScroll: true,
                        onFinish: () => setDeletingRecord(null),
                    });
                }}
            />
        </AppLayout>
    );
}

interface SortProps {
    sort: string;
    direction: 'asc' | 'desc';
    onSort: (sort: string, direction: SortDirection) => void;
}

function PlanTable({
    plans,
    sortProps,
    hasFilters,
    onCreate,
    onRun,
    onEdit,
    onDelete,
}: {
    plans: Paginated<MaintenancePlan> | null;
    sortProps: SortProps;
    hasFilters: boolean;
    onCreate: () => void;
    onRun: (plan: MaintenancePlan) => void;
    onEdit: (plan: MaintenancePlan) => void;
    onDelete: (plan: MaintenancePlan) => void;
}) {
    if (!plans || plans.data.length === 0) {
        return (
            <EmptyState
                icon={Wrench}
                title={hasFilters ? 'Nenhum plano encontrado' : 'Nenhum plano de manutenção'}
                hint={hasFilters ? 'Tente outro termo ou limpe os filtros.' : 'Cadastre os sites dos clientes que têm Hospedagem + Manutenção.'}
                action={hasFilters ? undefined : { label: 'Novo plano', onClick: onCreate }}
            />
        );
    }

    return (
        <>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                            <SortableHeader column="client" label="Cliente" className="pr-4 pl-6" {...sortProps} />
                            <SortableHeader column="site_url" label="Site" {...sortProps} />
                            <SortableHeader column="last_performed_at" label="Última manutenção" {...sortProps} />
                            <th className="px-4 py-2.5">Situação</th>
                            <th className="w-32 py-2.5 pr-6 pl-4" />
                        </tr>
                    </thead>
                    <tbody>
                        {plans.data.map((plan) => (
                            <tr
                                key={plan.id}
                                className={cn('hover:bg-muted/40 border-b transition-colors last:border-b-0', !plan.active && 'opacity-60')}
                            >
                                <td className="max-w-64 py-3 pr-4 pl-6">
                                    <Link href={route('clientes.show', plan.client.id)} className="hover:text-primary flex items-center gap-2">
                                        <ClientAvatar name={plan.client.name} photoUrl={plan.client.photo_url} className="size-7" />
                                        <span className="truncate">{plan.client.name}</span>
                                    </Link>
                                </td>

                                <td className="max-w-64 px-4 py-3">
                                    <span className="truncate font-medium">{plan.site_url}</span>
                                    {plan.notes && <div className="text-muted-foreground truncate text-xs">{plan.notes}</div>}
                                </td>

                                <td className="px-4 py-3">
                                    {plan.last_performed_label ? (
                                        <>
                                            <div className="tabular">{plan.last_performed_label}</div>
                                            {/* first-letter, não capitalize: "Agosto de 2026", não "Agosto De 2026". */}
                                            <div className="text-muted-foreground text-xs first-letter:uppercase">{plan.last_month_label}</div>
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground">Nunca</span>
                                    )}
                                </td>

                                <td className="px-4 py-3">
                                    <MaintenanceStatusBadge status={plan.status} pendingMonths={plan.pending_months} />
                                </td>

                                <td className="py-3 pr-6 pl-4">
                                    <div className="flex items-center justify-end gap-1">
                                        {/* Já feita no mês, registrar de novo é exceção — o botão sai do caminho. */}
                                        <Button
                                            size="sm"
                                            variant={plan.status === 'late' ? 'default' : plan.status === 'pending' ? 'outline' : 'ghost'}
                                            onClick={() => onRun(plan)}
                                        >
                                            <ClipboardCheck />
                                            Registrar
                                        </Button>

                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon-sm" aria-label={`Ações de ${plan.site_url}`}>
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem onSelect={() => onEdit(plan)}>
                                                    <Pencil className="size-4" />
                                                    Editar plano
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onSelect={() => onDelete(plan)} className="text-destructive focus:text-destructive">
                                                    <Trash2 className="size-4" />
                                                    Excluir
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Pagination page={plans} />
        </>
    );
}

function HistoryTable({
    history,
    sortProps,
    hasFilters,
    onEdit,
    onDelete,
}: {
    history: Paginated<MaintenanceRecord> | null;
    sortProps: SortProps;
    hasFilters: boolean;
    onEdit: (record: MaintenanceRecord) => void;
    onDelete: (record: MaintenanceRecord) => void;
}) {
    if (!history || history.data.length === 0) {
        return (
            <EmptyState
                icon={CalendarCheck}
                title={hasFilters ? 'Nada encontrado no histórico' : 'Nenhuma manutenção registrada'}
                hint={hasFilters ? 'Tente outro termo.' : 'O que for registrado nos planos aparece aqui.'}
            />
        );
    }

    return (
        <>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                            <SortableHeader column="performed_at" label="Data" className="pr-4 pl-6" {...sortProps} />
                            <SortableHeader column="client" label="Cliente" {...sortProps} />
                            <SortableHeader column="site" label="Site" {...sortProps} />
                            <th className="px-4 py-2.5">Checklist</th>
                            <th className="px-4 py-2.5">Relatório</th>
                            <th className="px-4 py-2.5">Executou</th>
                            <th className="w-12 py-2.5 pr-6 pl-4" />
                        </tr>
                    </thead>
                    <tbody>
                        {history.data.map((record) => (
                            <tr key={record.id} className="hover:bg-muted/40 border-b transition-colors last:border-b-0">
                                <td className="tabular py-3 pr-4 pl-6 font-medium">{record.performed_label}</td>

                                <td className="max-w-56 px-4 py-3">
                                    <Link href={route('clientes.show', record.client.id)} className="hover:text-primary flex items-center gap-2">
                                        <ClientAvatar name={record.client.name} photoUrl={record.client.photo_url} className="size-7" />
                                        <span className="truncate">{record.client.name}</span>
                                    </Link>
                                </td>

                                <td className="text-muted-foreground max-w-56 truncate px-4 py-3">{record.site_url}</td>

                                <td className="px-4 py-3">
                                    <span className="tabular">
                                        {record.done_count} de {record.total_count} realizados
                                    </span>
                                    {record.skipped_count > 0 && (
                                        <div className="text-warning text-xs">
                                            {record.skipped_count} {record.skipped_count === 1 ? 'pulado' : 'pulados'}
                                        </div>
                                    )}
                                </td>

                                <td className="px-4 py-3">
                                    {record.whatsapp_sent_at ? (
                                        <Badge variant="success">
                                            <CircleCheck />
                                            {record.whatsapp_sent_at}
                                        </Badge>
                                    ) : (
                                        <span className="flex items-center gap-1.5">
                                            <Badge variant="muted">Não enviado</Badge>
                                            {record.whatsapp_error && (
                                                <span title={record.whatsapp_error}>
                                                    <AlertTriangle className="text-warning size-3.5" />
                                                </span>
                                            )}
                                        </span>
                                    )}
                                </td>

                                <td className="text-muted-foreground px-4 py-3">{record.user ?? '—'}</td>

                                <td className="py-3 pr-6 pl-4 text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="icon-sm" aria-label={`Ações de ${record.site_url}`}>
                                                <MoreHorizontal />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem onSelect={() => onEdit(record)}>
                                                <Pencil className="size-4" />
                                                Editar
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    router.post(route('manutencao.registros.reenviar', record.id), {}, { preserveScroll: true })
                                                }
                                            >
                                                {record.whatsapp_sent_at ? <Send className="size-4" /> : <MessageCircle className="size-4" />}
                                                {record.whatsapp_sent_at ? 'Enviar de novo' : 'Enviar relatório'}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem onSelect={() => onDelete(record)} className="text-destructive focus:text-destructive">
                                                <Trash2 className="size-4" />
                                                Excluir
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <Pagination page={history} />
        </>
    );
}

function Tab({ active, onClick, label }: { active: boolean; onClick: () => void; label: string }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'relative cursor-pointer px-4 py-2.5 text-sm font-medium transition-colors',
                active ? 'text-foreground' : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {label}
            {active && <span className="bg-primary absolute inset-x-2 -bottom-px h-0.5 rounded-full" />}
        </button>
    );
}

function Stat({
    icon: Icon,
    label,
    value,
    hint,
    tone,
    active,
    onClick,
}: {
    icon: typeof Wrench;
    label: string;
    value: string;
    hint?: string;
    tone?: 'warning' | 'destructive';
    active?: boolean;
    onClick?: () => void;
}) {
    const clickable = onClick !== undefined;

    return (
        <Card
            onClick={onClick}
            className={cn('h-full', clickable && 'cursor-pointer hover:shadow-md', active && 'border-primary ring-primary/20 ring-2')}
        >
            <CardContent className="flex h-full flex-col gap-3 p-5">
                <div className="flex items-start justify-between gap-3">
                    <span className="text-muted-foreground text-sm font-medium">{label}</span>
                    <span
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            tone === 'warning' && 'bg-warning/10 text-warning',
                            tone === 'destructive' && 'bg-destructive/10 text-destructive',
                            !tone && 'bg-accent text-accent-foreground',
                        )}
                    >
                        <Icon className="size-4.5" />
                    </span>
                </div>

                <span className="tabular text-2xl font-bold tracking-tight">{value}</span>

                {hint && <span className="text-muted-foreground mt-auto pt-1 text-xs">{hint}</span>}
            </CardContent>
        </Card>
    );
}

function EmptyState({
    icon: Icon,
    title,
    hint,
    action,
}: {
    icon: typeof Wrench;
    title: string;
    hint: string;
    action?: { label: string; onClick: () => void };
}) {
    return (
        <div className="flex flex-col items-center gap-3 border-t px-6 py-16 text-center">
            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                <Icon className="size-5" />
            </span>

            <div className="space-y-1">
                <p className="text-sm font-medium">{title}</p>
                <p className="text-muted-foreground text-sm">{hint}</p>
            </div>

            {action && (
                <Button onClick={action.onClick} variant="outline" className="mt-1">
                    <Plus />
                    {action.label}
                </Button>
            )}
        </div>
    );
}
