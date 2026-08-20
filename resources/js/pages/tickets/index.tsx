import { ClientAvatar } from '@/components/clients/client-avatar';
import { TicketFormSheet } from '@/components/tickets/ticket-form-sheet';
import { TicketPriorityBadge, TicketStatusBadge } from '@/components/tickets/ticket-badges';
import { TicketsViewToggle } from '@/components/tickets/view-toggle';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { ClientOption } from '@/types/domains';
import {
    ticketPriorityLabels,
    ticketPriorityOrder,
    ticketStatusLabels,
    ticketStatusOrder,
    type AgentOption,
    type TicketFilters,
    type TicketListItem,
    type TicketStats,
} from '@/types/tickets';
import { Head, router } from '@inertiajs/react';
import { CircleAlert, Inbox, LifeBuoy, Plus, Search, UserX } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tickets', href: '/tickets' },
];

interface TicketsPageProps {
    tickets: Paginated<TicketListItem>;
    filters: TicketFilters;
    stats: TicketStats;
    clients: ClientOption[];
    agents: AgentOption[];
    categories: string[];
}

export default function Tickets({ tickets, filters, stats, clients, agents }: TicketsPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [creating, setCreating] = useState(false);
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

    function apply(overrides: Partial<TicketFilters>) {
        router.get(route('tickets.index'), { ...filters, search, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    }

    /** Select de filtro: 'all' vira vazio. */
    const pick = (key: keyof TicketFilters) => (value: string) => apply({ [key]: value === 'all' ? '' : value } as Partial<TicketFilters>);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tickets" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Tickets</h1>
                        <p className="text-muted-foreground text-sm">O atendimento da agência — o que entrou, com quem está e o que falta responder.</p>
                    </div>

                    <div className="flex items-center gap-2">
                        <TicketsViewToggle current="list" />
                        <Button onClick={() => setCreating(true)}>
                            <Plus />
                            Novo ticket
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat icon={Inbox} label="Abertos" value={formatNumber(stats.open)} hint="Aberto ou em andamento" />
                    <Stat icon={UserX} label="Sem responsável" value={formatNumber(stats.unassigned)} hint="Esperando alguém assumir" tone={stats.unassigned > 0} />
                    <Stat icon={CircleAlert} label="Urgentes" value={formatNumber(stats.urgent)} hint="Prioridade urgente, em aberto" tone={stats.urgent > 0} />
                    <Stat icon={LifeBuoy} label="Resolvidos" value={formatNumber(stats.resolved)} hint="Aguardando fechamento" />
                </div>

                <Card>
                    <div className="flex flex-wrap items-center gap-2 p-4">
                        <div className="min-w-56 flex-1">
                            <Input startIcon={Search} value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar por número, assunto ou cliente…" />
                        </div>

                        <Select value={filters.status || 'all'} onValueChange={pick('status')}>
                            <SelectTrigger className="w-40" aria-label="Situação">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Qualquer situação</SelectItem>
                                {ticketStatusOrder.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {ticketStatusLabels[s]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={filters.priority || 'all'} onValueChange={pick('priority')}>
                            <SelectTrigger className="w-36" aria-label="Prioridade">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Qualquer prioridade</SelectItem>
                                {ticketPriorityOrder.map((p) => (
                                    <SelectItem key={p} value={p}>
                                        {ticketPriorityLabels[p]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={filters.assignee || 'all'} onValueChange={pick('assignee')}>
                            <SelectTrigger className="w-44" aria-label="Responsável">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Qualquer responsável</SelectItem>
                                <SelectItem value="unassigned">Sem responsável</SelectItem>
                                {agents.map((a) => (
                                    <SelectItem key={a.value} value={a.value}>
                                        {a.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {tickets.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 border-t px-6 py-16 text-center">
                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                <LifeBuoy className="size-5" />
                            </span>
                            <p className="text-sm font-medium">Nenhum ticket por aqui</p>
                            <Button variant="outline" onClick={() => setCreating(true)}>
                                <Plus />
                                Abrir o primeiro
                            </Button>
                        </div>
                    ) : (
                        <>
                            <div className="min-w-0 overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                            <th className="py-2.5 pr-4 pl-6">Ticket</th>
                                            <th className="px-4 py-2.5">Cliente</th>
                                            <th className="px-4 py-2.5">Responsável</th>
                                            <th className="px-4 py-2.5">Prioridade</th>
                                            <th className="px-4 py-2.5 text-right">Situação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tickets.data.map((ticket) => (
                                            <tr
                                                key={ticket.id}
                                                onClick={() => router.visit(route('tickets.show', ticket.id))}
                                                className="hover:bg-muted/40 cursor-pointer border-b transition-colors last:border-b-0"
                                            >
                                                <td className="max-w-96 py-3 pr-4 pl-6">
                                                    <div className="flex items-center gap-2 font-medium">
                                                        <span className="tabular text-muted-foreground text-xs">{ticket.number}</span>
                                                        <span className="truncate">{ticket.subject}</span>
                                                    </div>
                                                    <div className="text-muted-foreground mt-0.5 text-xs">
                                                        {ticket.messages_count} {ticket.messages_count === 1 ? 'mensagem' : 'mensagens'}
                                                        {ticket.last_reply_label ? ` · ${ticket.last_reply_label}` : ''}
                                                    </div>
                                                </td>

                                                <td className="max-w-48 px-4 py-3">
                                                    {ticket.client ? (
                                                        <div className="flex items-center gap-2">
                                                            <ClientAvatar name={ticket.client.name} photoUrl={ticket.client.photo_url} className="size-7" />
                                                            <span className="truncate">{ticket.client.name}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </td>

                                                <td className="text-muted-foreground px-4 py-3">{ticket.assignee?.name ?? 'Sem responsável'}</td>

                                                <td className="px-4 py-3">
                                                    <TicketPriorityBadge priority={ticket.priority} />
                                                </td>

                                                <td className="px-4 py-3 text-right">
                                                    <TicketStatusBadge status={ticket.status} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <Pagination page={tickets} />
                        </>
                    )}
                </Card>
            </div>

            <TicketFormSheet open={creating} onOpenChange={setCreating} clients={clients} agents={agents} />
        </AppLayout>
    );
}

function Stat({ icon: Icon, label, value, hint, tone }: { icon: typeof Inbox; label: string; value: string; hint: string; tone?: boolean }) {
    return (
        <Card className="p-4">
            <div className="flex items-start justify-between gap-2">
                <span className="text-muted-foreground text-sm font-medium">{label}</span>
                <span className={cn('flex size-7 items-center justify-center rounded-lg', tone ? 'bg-warning/15 text-warning' : 'bg-muted text-muted-foreground')}>
                    <Icon className="size-4" />
                </span>
            </div>
            <div className="mt-2 text-2xl font-bold tracking-tight tabular">{value}</div>
            <div className="text-muted-foreground mt-1 text-xs">{hint}</div>
        </Card>
    );
}
