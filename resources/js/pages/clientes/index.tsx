import { ClientAvatar } from '@/components/clients/client-avatar';
import { ClientFormSheet } from '@/components/clients/client-form-sheet';
import { ClientQuickView } from '@/components/clients/client-quick-view';
import { DeleteClientDialog } from '@/components/clients/delete-client-dialog';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { FilterChip } from '@/components/ui/filter-chip';
import { Input } from '@/components/ui/input';
import { SortableHeader, type SortDirection } from '@/components/ui/sortable-header';
import { StatusPicker } from '@/components/ui/status-picker';
import { clientStatusOptions } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatNumber } from '@/lib/format';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Client, ClientFilters, ClientStats } from '@/types/clients';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowUpRight, Building2, CircleCheck, CircleSlash, Eye, MoreHorizontal, Pencil, Plus, Search, Trash2, UserRound, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Clientes', href: '/clientes' },
];

interface ClientesPageProps {
    clients: Paginated<Client>;
    filters: ClientFilters;
    stats: ClientStats;
}

export default function Clientes({ clients, filters, stats }: ClientesPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Client | null>(null);
    const [deleting, setDeleting] = useState<Client | null>(null);
    const [previewing, setPreviewing] = useState<Client | null>(null);
    const firstRender = useRef(true);

    // Busca com debounce: cada tecla nao vira uma requisicao.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => apply({ search }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    function apply(overrides: Partial<ClientFilters>) {
        router.get(
            route('clientes.index'),
            { search, status: filters.status, type: filters.type, sort: filters.sort, direction: filters.direction, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const hasFilters = Boolean(filters.search || filters.status || filters.type);

    const sortProps = {
        sort: filters.sort,
        direction: filters.direction,
        onSort: (sort: string, direction: SortDirection) => apply({ sort, direction }),
    };

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(client: Client) {
        setEditing(client);
        setFormOpen(true);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clientes" />

            <div className="animate-fade-in flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Clientes</h1>
                        <p className="text-muted-foreground text-sm">
                            <span className="tabular">{formatNumber(stats.total)}</span> cadastrados
                        </p>
                    </div>

                    <Button onClick={openCreate}>
                        <Plus />
                        Novo cliente
                    </Button>
                </div>

                {/* Os indicadores sao os proprios filtros: clicar num deles recorta a
                    lista, e clicar de novo desfaz. */}
                <div className="flex flex-wrap gap-2">
                    <FilterChip
                        icon={Users}
                        label="Todos"
                        count={stats.total}
                        active={!filters.status && !filters.type}
                        onClick={() => apply({ status: '', type: '' })}
                    />
                    <FilterChip
                        icon={CircleCheck}
                        label="Ativos"
                        count={stats.active}
                        tone="success"
                        active={filters.status === 'active'}
                        onClick={() => apply({ status: filters.status === 'active' ? '' : 'active' })}
                    />
                    <FilterChip
                        icon={CircleSlash}
                        label="Inativos"
                        count={stats.inactive}
                        active={filters.status === 'inactive'}
                        onClick={() => apply({ status: filters.status === 'inactive' ? '' : 'inactive' })}
                    />
                    <FilterChip
                        icon={Building2}
                        label="Pessoa jurídica"
                        count={stats.company}
                        active={filters.type === 'company'}
                        onClick={() => apply({ type: filters.type === 'company' ? '' : 'company' })}
                    />
                    <FilterChip
                        icon={UserRound}
                        label="Pessoa física"
                        count={stats.person}
                        active={filters.type === 'person'}
                        onClick={() => apply({ type: filters.type === 'person' ? '' : 'person' })}
                    />
                </div>

                <Card>
                    <div className="flex flex-wrap items-center gap-3 p-4">
                        <div className="min-w-56 flex-1">
                            <Input
                                startIcon={Search}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar nome, e-mail ou documento…"
                            />
                        </div>

                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    apply({ search: '', status: '', type: '' });
                                }}
                            >
                                Limpar filtros
                            </Button>
                        )}
                    </div>

                    {clients.data.length === 0 ? (
                        <EmptyState hasFilters={hasFilters} onCreate={openCreate} />
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                            <SortableHeader column="name" label="Cliente" className="pr-4 pl-6" {...sortProps} />
                                            <SortableHeader column="email" label="Contato" {...sortProps} />
                                            <SortableHeader column="city" label="Cidade" {...sortProps} />
                                            <SortableHeader column="monthly_fee" label="Mensalidade" align="right" {...sortProps} />
                                            <SortableHeader column="status" label="Situação" {...sortProps} />
                                            <th className="w-12 py-2.5 pr-6 pl-4" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {clients.data.map((client) => {
                                            return (
                                                <tr
                                                    key={client.id}
                                                    onClick={() => router.visit(route('clientes.show', client.id))}
                                                    className="group/row hover:bg-muted/40 cursor-pointer border-b transition-colors last:border-b-0"
                                                >
                                                    <td className="py-3 pr-4 pl-6">
                                                        <div className="flex items-center gap-3">
                                                            <ClientAvatar name={client.name} photoUrl={client.photo_url} />

                                                            <div className="min-w-0">
                                                                {/* Link de verdade: teclado, leitor de tela e "abrir em nova aba"
                                                                    funcionam, mesmo com a linha inteira sendo clicável. */}
                                                                <Link
                                                                    href={route('clientes.show', client.id)}
                                                                    onClick={(event) => event.stopPropagation()}
                                                                    className="hover:text-primary block truncate font-medium"
                                                                >
                                                                    {client.name}
                                                                </Link>
                                                                <div className="text-muted-foreground truncate text-xs">
                                                                    {client.trade_name ||
                                                                        client.document ||
                                                                        (client.type === 'person' ? 'Pessoa física' : 'Pessoa jurídica')}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="text-muted-foreground">{client.email ?? '—'}</div>
                                                        {client.phone && <div className="tabular text-muted-foreground text-xs">{client.phone}</div>}
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-3">
                                                        {client.city ? `${client.city}${client.state ? `/${client.state}` : ''}` : '—'}
                                                    </td>
                                                    <td className="tabular px-4 py-3 text-right">
                                                        {client.monthly_fee === null ? (
                                                            <span className="text-muted-foreground">—</span>
                                                        ) : (
                                                            formatCurrency(client.monthly_fee)
                                                        )}
                                                    </td>
                                                    {/* Trocar a situação aqui mesmo, sem abrir o cadastro. */}
                                                    <td className="px-4 py-3" onClick={(event) => event.stopPropagation()}>
                                                        <StatusPicker
                                                            value={client.status}
                                                            options={clientStatusOptions}
                                                            url={route('clientes.status', client.id)}
                                                            field="status"
                                                            label="situação"
                                                        />
                                                    </td>
                                                    {/* A célula de ações não propaga o clique: senão abrir o menu
                                                        levaria embora para a página do cliente. */}
                                                    <td className="py-3 pr-6 pl-4 text-right" onClick={(event) => event.stopPropagation()}>
                                                        <div className="flex items-center justify-end gap-0.5">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon-sm"
                                                                aria-label={`Visualização rápida de ${client.name}`}
                                                                title="Visualização rápida"
                                                                onClick={() => setPreviewing(client)}
                                                                className="opacity-0 transition-opacity group-hover/row:opacity-100 focus-visible:opacity-100"
                                                            >
                                                                <Eye />
                                                            </Button>

                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild>
                                                                    <Button variant="ghost" size="icon-sm" aria-label={`Ações de ${client.name}`}>
                                                                        <MoreHorizontal />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end">
                                                                    <DropdownMenuItem onSelect={() => setPreviewing(client)}>
                                                                        <Eye className="size-4" />
                                                                        Visualização rápida
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem asChild>
                                                                        <Link href={route('clientes.show', client.id)}>
                                                                            <ArrowUpRight className="size-4" />
                                                                            Abrir página
                                                                        </Link>
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem onSelect={() => openEdit(client)}>
                                                                        <Pencil className="size-4" />
                                                                        Editar
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem
                                                                        onSelect={() => setDeleting(client)}
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
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            <Pagination page={clients} />
                        </>
                    )}
                </Card>
            </div>

            <ClientQuickView
                client={previewing}
                onOpenChange={() => setPreviewing(null)}
                onEdit={(client) => {
                    setPreviewing(null);
                    openEdit(client);
                }}
            />

            <ClientFormSheet open={formOpen} onOpenChange={setFormOpen} client={editing} />
            <DeleteClientDialog client={deleting} onOpenChange={() => setDeleting(null)} />
        </AppLayout>
    );
}

function EmptyState({ hasFilters, onCreate }: { hasFilters: boolean; onCreate: () => void }) {
    return (
        <div className="flex flex-col items-center gap-3 border-t px-6 py-16 text-center">
            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                <Users className="size-5" />
            </span>

            <div className="space-y-1">
                <p className="text-sm font-medium">{hasFilters ? 'Nenhum cliente encontrado' : 'Nenhum cliente cadastrado'}</p>
                <p className="text-muted-foreground text-sm">
                    {hasFilters ? 'Tente outro termo de busca ou limpe os filtros.' : 'Cadastre o primeiro cliente da agência.'}
                </p>
            </div>

            {!hasFilters && (
                <Button onClick={onCreate} variant="outline" className="mt-1">
                    <Plus />
                    Novo cliente
                </Button>
            )}
        </div>
    );
}
