import { ClientAvatar } from '@/components/clients/client-avatar';
import { DeleteDomainDialog } from '@/components/domains/delete-domain-dialog';
import { DomainFormSheet } from '@/components/domains/domain-form-sheet';
import { DomainStatusBadge } from '@/components/domains/domain-status-badge';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SortableHeader, type SortDirection } from '@/components/ui/sortable-header';
import { StatusPicker } from '@/components/ui/status-picker';
import { domainManagedByOptions } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { ClientOption, Domain, DomainFilters, DomainStats, DomainWithClient } from '@/types/domains';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Building2, CalendarClock, Globe, MoreHorizontal, Pencil, Plus, RefreshCw, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Domínios', href: '/dominios' },
];

interface DominiosPageProps {
    domains: Paginated<DomainWithClient>;
    filters: DomainFilters;
    stats: DomainStats;
    clients: ClientOption[];
}

export default function Dominios({ domains, filters, stats, clients }: DominiosPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Domain | null>(null);
    const [deleting, setDeleting] = useState<Domain | null>(null);
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

    function apply(overrides: Partial<DomainFilters>) {
        const next = {
            search,
            managed_by: filters.managed_by,
            status: filters.status,
            sort: filters.sort,
            direction: filters.direction,
            ...overrides,
        };

        router.get(route('dominios.index'), next, { preserveState: true, preserveScroll: true, replace: true });
    }

    const sortProps = {
        sort: filters.sort,
        direction: filters.direction,
        onSort: (sort: string, direction: SortDirection) => apply({ sort, direction }),
    };

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(domain: Domain) {
        setEditing(domain);
        setFormOpen(true);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Domínios" />

            {/* min-w-0: sem isso a tabela larga estica o flex container e empurra os cards para fora da tela. */}
            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Domínios</h1>
                        <p className="text-muted-foreground text-sm">Onde cada domínio está registrado e quem cuida da renovação.</p>
                    </div>

                    <Button onClick={openCreate}>
                        <Plus />
                        Novo domínio
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat icon={Globe} label="Domínios" value={formatNumber(stats.total)} hint={`${formatNumber(stats.client)} com o cliente`} />
                    <Stat
                        icon={Building2}
                        label="Sob nossa gestão"
                        value={formatNumber(stats.agency)}
                        hint="Somos nós que renovamos"
                        active={filters.managed_by === 'agency'}
                        onClick={() => apply({ managed_by: filters.managed_by === 'agency' ? '' : 'agency', status: '' })}
                    />
                    <Stat
                        icon={CalendarClock}
                        label="A renovar"
                        value={formatNumber(stats.expiring)}
                        hint="Vencem em até 30 dias"
                        tone={stats.expiring > 0 ? 'warning' : undefined}
                        active={filters.status === 'expiring'}
                        onClick={() => apply({ status: filters.status === 'expiring' ? '' : 'expiring', managed_by: 'agency' })}
                    />
                    <Stat
                        icon={AlertTriangle}
                        label="Vencidos"
                        value={formatNumber(stats.expired)}
                        hint="Precisam de ação agora"
                        tone={stats.expired > 0 ? 'destructive' : undefined}
                        active={filters.status === 'expired'}
                        onClick={() => apply({ status: filters.status === 'expired' ? '' : 'expired', managed_by: 'agency' })}
                    />
                </div>

                <Card>
                    <div className="flex flex-wrap items-center gap-3 p-4">
                        <div className="min-w-56 flex-1">
                            <Input
                                startIcon={Search}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar domínio, cliente ou registrador…"
                            />
                        </div>

                        <Select value={filters.managed_by || 'all'} onValueChange={(value) => apply({ managed_by: value === 'all' ? '' : value })}>
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Qualquer gestão</SelectItem>
                                <SelectItem value="agency">Gerimos nós</SelectItem>
                                <SelectItem value="client">Cliente cuida</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select value={filters.status || 'all'} onValueChange={(value) => apply({ status: value === 'all' ? '' : value })}>
                            <SelectTrigger className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Qualquer situação</SelectItem>
                                <SelectItem value="expired">Vencidos</SelectItem>
                                <SelectItem value="expiring">Vencem em 30 dias</SelectItem>
                                <SelectItem value="ok">Em dia</SelectItem>
                                <SelectItem value="unknown">Sem data</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {domains.data.length === 0 ? (
                        <EmptyState hasFilters={Boolean(filters.search || filters.managed_by || filters.status)} onCreate={openCreate} />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                            <SortableHeader column="name" label="Domínio" className="pr-4 pl-6" {...sortProps} />
                                            <SortableHeader column="client" label="Cliente" {...sortProps} />
                                            <SortableHeader column="registrar" label="Registrador" {...sortProps} />
                                            <SortableHeader column="managed_by" label="Gestão" {...sortProps} />
                                            <SortableHeader column="expires_at" label="Vencimento" {...sortProps} />
                                            <SortableHeader column="annual_cost" label="Custo/ano" align="right" {...sortProps} />
                                            <th className="w-12 py-2.5 pr-6 pl-4" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {domains.data.map((domain) => (
                                            <tr key={domain.id} className="hover:bg-muted/40 border-b transition-colors last:border-b-0">
                                                <td className="max-w-72 py-3 pr-4 pl-6">
                                                    <div className="flex items-center gap-2 font-medium">
                                                        <span className="truncate">{domain.name}</span>
                                                        {domain.auto_renew && (
                                                            <span title="Renovação automática ativa">
                                                                <RefreshCw className="text-muted-foreground size-3.5 shrink-0" />
                                                            </span>
                                                        )}
                                                    </div>
                                                    {domain.notes && <div className="text-muted-foreground truncate text-xs">{domain.notes}</div>}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={route('clientes.show', domain.client.id)}
                                                        className="hover:text-primary flex items-center gap-2"
                                                    >
                                                        {/* Sem tamanho de fonte próprio: as iniciais herdam a escala do sistema. */}
                                                        <ClientAvatar
                                                            name={domain.client.name}
                                                            photoUrl={domain.client.photo_url}
                                                            className="size-7"
                                                        />
                                                        <span className="truncate">{domain.client.name}</span>
                                                    </Link>
                                                </td>

                                                <td className="text-muted-foreground px-4 py-3">{domain.registrar ?? '—'}</td>

                                                {/* Passar a renovação para o cliente (ou trazer para nós) sem abrir o cadastro. */}
                                                <td className="px-4 py-3">
                                                    <StatusPicker
                                                        value={domain.managed_by}
                                                        options={domainManagedByOptions}
                                                        url={route('dominios.gestao', domain.id)}
                                                        field="managed_by"
                                                        label="gestão"
                                                    />
                                                </td>

                                                <td className="px-4 py-3">
                                                    <DomainStatusBadge status={domain.status} daysLeft={domain.days_left} />
                                                    {domain.expires_at_label && (
                                                        <div className="tabular text-muted-foreground mt-0.5 text-xs">{domain.expires_at_label}</div>
                                                    )}
                                                </td>

                                                <td className="tabular px-4 py-3 text-right">
                                                    {domain.annual_cost === null ? (
                                                        <span className="text-muted-foreground">—</span>
                                                    ) : (
                                                        formatCurrency(domain.annual_cost)
                                                    )}
                                                </td>

                                                <td className="py-3 pr-6 pl-4 text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon-sm" aria-label={`Ações de ${domain.name}`}>
                                                                <MoreHorizontal />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem onSelect={() => openEdit(domain)}>
                                                                <Pencil className="size-4" />
                                                                Editar
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={route('clientes.show', domain.client.id)}>
                                                                    <Building2 className="size-4" />
                                                                    Ver cliente
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onSelect={() => setDeleting(domain)}
                                                                className="text-destructive focus:text-destructive"
                                                            >
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

                            <Pagination page={domains} />
                        </>
                    )}
                </Card>
            </div>

            <DomainFormSheet open={formOpen} onOpenChange={setFormOpen} domain={editing} clients={clients} />
            <DeleteDomainDialog domain={deleting} onOpenChange={() => setDeleting(null)} />
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
    icon: typeof Globe;
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

function EmptyState({ hasFilters, onCreate }: { hasFilters: boolean; onCreate: () => void }) {
    return (
        <div className="flex flex-col items-center gap-3 border-t px-6 py-16 text-center">
            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                <Globe className="size-5" />
            </span>

            <div className="space-y-1">
                <p className="text-sm font-medium">{hasFilters ? 'Nenhum domínio encontrado' : 'Nenhum domínio cadastrado'}</p>
                <p className="text-muted-foreground text-sm">
                    {hasFilters ? 'Tente outro termo ou limpe os filtros.' : 'Cadastre o primeiro domínio e vincule a um cliente.'}
                </p>
            </div>

            {!hasFilters && (
                <Button onClick={onCreate} variant="outline" className="mt-1">
                    <Plus />
                    Novo domínio
                </Button>
            )}
        </div>
    );
}
