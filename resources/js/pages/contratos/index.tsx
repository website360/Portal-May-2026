import { ClientAvatar } from '@/components/clients/client-avatar';
import { ContractRenewDialog } from '@/components/contracts/contract-renew-dialog';
import { ContractSheet } from '@/components/contracts/contract-sheet';
import { ContractTabs } from '@/components/contracts/contract-tabs';
import { ContractStatusBadge } from '@/components/contracts/contract-status-badge';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { MultiSelect } from '@/components/ui/multi-select';
import { SortableHeader, type SortDirection } from '@/components/ui/sortable-header';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import { CONTRACT_FILTER_KEYS, statusOptions, type ClientOption, type Contract, type ContractFilters, type ContractStats } from '@/types/contracts';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    CalendarClock,
    CircleCheck,
    Download,
    Eye,
    FileClock,
    FilePlus,
    FileSignature,
    FileText,
    MoreHorizontal,
    Paperclip,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contratos', href: '/contratos' },
];

interface ContratosPageProps {
    contracts: Paginated<Contract>;
    filters: ContractFilters;
    stats: ContractStats;
    clients: ClientOption[];
    services: { value: string; label: string }[];
}

export default function Contratos({ contracts, filters, stats, clients, services }: ContratosPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<Contract | null>(null);
    const [creating, setCreating] = useState(false);
    const [renewing, setRenewing] = useState<Contract | null>(null);
    const [deleting, setDeleting] = useState<Contract | null>(null);
    const [cancelling, setCancelling] = useState<Contract | null>(null);
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

    function apply(overrides: Partial<ContractFilters>) {
        router.get(
            route('contratos.index'),
            {
                search,
                statuses: filters.statuses,
                clients: filters.clients,
                services: filters.services,
                sort: filters.sort,
                direction: filters.direction,
                ...overrides,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function clearFilters() {
        setSearch('');
        apply({ search: '', statuses: [], clients: [], services: [] });
    }

    const sortProps = {
        sort: filters.sort,
        direction: filters.direction,
        onSort: (sort: string, direction: SortDirection) => apply({ sort, direction }),
    };

    const hasFilters = Boolean(filters.search) || CONTRACT_FILTER_KEYS.some((key) => filters[key].length > 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contratos" />

            {/* min-w-0: sem isso a tabela larga estica o flex e empurra os cards para fora. */}
            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <ContractTabs current="/contratos" />

                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Contratos</h1>
                        <p className="text-muted-foreground text-sm">O que cada cliente contratou, a vigência e o documento.</p>
                    </div>

                    <Button variant="outline" onClick={() => setCreating(true)}>
                        <FilePlus />
                        Novo contrato
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat icon={FileText} label="Contratos" value={formatNumber(stats.total)} hint="Na base inteira" />
                    <Stat
                        icon={CircleCheck}
                        label="Vigentes"
                        value={formatNumber(stats.active)}
                        hint="Assinados e dentro do prazo"
                        active={filters.statuses.includes('active')}
                        onClick={() => apply({ statuses: filters.statuses.includes('active') ? [] : ['active'] })}
                    />
                    <Stat
                        icon={AlertTriangle}
                        label="A renovar"
                        value={formatNumber(stats.expiring)}
                        hint="Vencem em até 30 dias"
                        tone={stats.expiring > 0 ? 'warning' : undefined}
                        active={filters.statuses.includes('expiring')}
                        onClick={() => apply({ statuses: filters.statuses.includes('expiring') ? [] : ['expiring'] })}
                    />
                    <Stat
                        icon={FileClock}
                        label="Sem assinatura"
                        value={formatNumber(stats.draft)}
                        hint="Gerados e não assinados"
                        tone={stats.draft > 0 ? 'warning' : undefined}
                        active={filters.statuses.includes('draft')}
                        onClick={() => apply({ statuses: filters.statuses.includes('draft') ? [] : ['draft'] })}
                    />
                </div>

                <Card>
                    <div className="flex flex-wrap items-center gap-2 p-4">
                        <div className="min-w-56 flex-1">
                            <Input
                                startIcon={Search}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por número, título, serviço ou cliente…"
                            />
                        </div>

                        <MultiSelect
                            value={filters.statuses}
                            onChange={(statuses) => apply({ statuses })}
                            options={statusOptions}
                            allLabel="Qualquer situação"
                            aria-label="Situação"
                            className="w-48"
                        />

                        {services.length > 1 && (
                            <MultiSelect
                                value={filters.services}
                                onChange={(selected) => apply({ services: selected })}
                                options={services}
                                allLabel="Qualquer serviço"
                                searchPlaceholder="Buscar serviço…"
                                aria-label="Serviço"
                                className="w-48"
                            />
                        )}

                        <MultiSelect
                            value={filters.clients}
                            onChange={(selected) => apply({ clients: selected })}
                            options={clients.map((c) => ({ value: String(c.id), label: c.name }))}
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

                    {contracts.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 border-t px-6 py-16 text-center">
                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                <FileText className="size-5" />
                            </span>
                            <div className="space-y-1">
                                <p className="text-sm font-medium">{hasFilters ? 'Nenhum contrato encontrado' : 'Nenhum contrato ainda'}</p>
                                <p className="text-muted-foreground text-sm">
                                    {hasFilters ? 'Tente outro termo ou limpe os filtros.' : 'Gere o primeiro a partir de um modelo.'}
                                </p>
                            </div>
                            {!hasFilters && (
                                <Button variant="outline" className="mt-1" asChild>
                                    <Link href={route('contratos.gerar')}>
                                        <Plus />
                                        Gerar contrato
                                    </Link>
                                </Button>
                            )}
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                            <SortableHeader column="number" label="Número" className="pr-4 pl-6" {...sortProps} />
                                            <SortableHeader column="client" label="Cliente" {...sortProps} />
                                            <SortableHeader column="service" label="Serviço" {...sortProps} />
                                            <SortableHeader column="value" label="Valor" align="right" {...sortProps} />
                                            <SortableHeader column="ends_at" label="Vigência" {...sortProps} />
                                            <th className="px-4 py-2.5">Situação</th>
                                            <th className="w-24 py-2.5 pr-6 pl-4" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {contracts.data.map((contract) => (
                                            <tr
                                                key={contract.id}
                                                className={cn(
                                                    'hover:bg-muted/40 border-b transition-colors last:border-b-0',
                                                    contract.cancelled && 'opacity-60',
                                                )}
                                            >
                                                <td className="py-3 pr-4 pl-6">
                                                    <div className="tabular flex items-center gap-1.5 font-medium">
                                                        {contract.number}
                                                        {contract.has_attachment && (
                                                            <span title="Tem PDF anexado">
                                                                <Paperclip className="text-muted-foreground size-3.5" />
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="text-muted-foreground truncate text-xs">{contract.title}</div>
                                                </td>

                                                <td className="max-w-56 px-4 py-3">
                                                    <Link
                                                        href={route('clientes.show', contract.client.id)}
                                                        className="hover:text-primary flex items-center gap-2"
                                                    >
                                                        <ClientAvatar
                                                            name={contract.client.name}
                                                            photoUrl={contract.client.photo_url}
                                                            className="size-7"
                                                        />
                                                        <span className="truncate">{contract.client.name}</span>
                                                    </Link>
                                                </td>

                                                <td className="text-muted-foreground max-w-48 truncate px-4 py-3">{contract.service}</td>

                                                <td className="tabular px-4 py-3 text-right">
                                                    {contract.value === null ? (
                                                        <span className="text-muted-foreground">—</span>
                                                    ) : (
                                                        formatCurrency(contract.value)
                                                    )}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="tabular">{contract.starts_label}</div>
                                                    <div className="text-muted-foreground text-xs">
                                                        {contract.ends_label ? `até ${contract.ends_label}` : 'prazo indeterminado'}
                                                    </div>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <ContractStatusBadge status={contract.status} daysLeft={contract.days_left} />
                                                </td>

                                                <td className="py-3 pr-6 pl-4">
                                                    <div className="flex items-center justify-end gap-1">
                                                        {(contract.has_attachment || contract.has_body) && (
                                                            <Button variant="ghost" size="icon-sm" aria-label="Baixar PDF" asChild>
                                                                <a href={route('contratos.pdf', contract.id)}>
                                                                    <Download />
                                                                </a>
                                                            </Button>
                                                        )}

                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" size="icon-sm" aria-label={`Ações de ${contract.number}`}>
                                                                    <MoreHorizontal />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                {(contract.has_attachment || contract.has_body) && (
                                                                    <DropdownMenuItem asChild>
                                                                        {/* Ver sem baixar: conferir não precisa encher a pasta de downloads. */}
                                                                        <a
                                                                            href={`${route('contratos.pdf', contract.id)}?ver=1`}
                                                                            target="_blank"
                                                                            rel="noreferrer"
                                                                        >
                                                                            <Eye className="size-4" />
                                                                            Visualizar
                                                                        </a>
                                                                    </DropdownMenuItem>
                                                                )}

                                                                <DropdownMenuItem onSelect={() => setEditing(contract)}>
                                                                    <Pencil className="size-4" />
                                                                    Editar e anexar
                                                                </DropdownMenuItem>

                                                                {contract.status === 'draft' && (
                                                                    <DropdownMenuItem onSelect={() => setEditing(contract)}>
                                                                        <FileSignature className="size-4" />
                                                                        Marcar como assinado
                                                                    </DropdownMenuItem>
                                                                )}

                                                                {contract.ends_at && !contract.cancelled && contract.status !== 'draft' && (
                                                                    <DropdownMenuItem onSelect={() => setRenewing(contract)}>
                                                                        <CalendarClock className="size-4" />
                                                                        Renovar
                                                                    </DropdownMenuItem>
                                                                )}

                                                                <DropdownMenuSeparator />

                                                                <DropdownMenuItem onSelect={() => setCancelling(contract)}>
                                                                    <Ban className="size-4" />
                                                                    {contract.cancelled ? 'Reativar' : 'Cancelar'}
                                                                </DropdownMenuItem>

                                                                <DropdownMenuItem
                                                                    onSelect={() => setDeleting(contract)}
                                                                    className="text-destructive focus:text-destructive"
                                                                >
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

                            <Pagination page={contracts} />
                        </>
                    )}
                </Card>
            </div>

            <ContractSheet
                open={editing !== null || creating}
                contract={editing}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                        setCreating(false);
                    }
                }}
                clients={clients}
            />

            <ContractRenewDialog contract={renewing} onClose={() => setRenewing(null)} />

            <ConfirmDialog
                open={cancelling !== null}
                onOpenChange={() => setCancelling(null)}
                title={cancelling?.cancelled ? `Reativar o contrato ${cancelling?.number}?` : `Cancelar o contrato ${cancelling?.number}?`}
                description={
                    cancelling?.cancelled
                        ? 'O contrato volta a contar a vigência normalmente.'
                        : 'O contrato continua no histórico do cliente, marcado como cancelado. Nada é apagado.'
                }
                confirmLabel={cancelling?.cancelled ? 'Reativar' : 'Cancelar contrato'}
                destructive={!cancelling?.cancelled}
                onConfirm={() => {
                    if (!cancelling) return;
                    router.post(route('contratos.cancelamento', cancelling.id), {}, { preserveScroll: true, onFinish: () => setCancelling(null) });
                }}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={() => setDeleting(null)}
                title={`Excluir o contrato ${deleting?.number}?`}
                description="O texto e o PDF anexado vão junto, e não dá para desfazer. Para tirar de circulação sem perder o histórico, use Cancelar."
                onConfirm={() => {
                    if (!deleting) return;
                    router.delete(route('contratos.destroy', deleting.id), { preserveScroll: true, onFinish: () => setDeleting(null) });
                }}
            />
        </AppLayout>
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
    icon: typeof FileText;
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
