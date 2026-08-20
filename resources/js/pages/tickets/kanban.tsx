import { ClientAvatar } from '@/components/clients/client-avatar';
import { TicketFormSheet } from '@/components/tickets/ticket-form-sheet';
import { TicketPriorityBadge } from '@/components/tickets/ticket-badges';
import { TicketsViewToggle } from '@/components/tickets/view-toggle';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { ClientOption } from '@/types/domains';
import { ticketStatusLabels, ticketStatusOrder, type AgentOption, type TicketListItem, type TicketStats, type TicketStatus } from '@/types/tickets';
import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Columns = Record<TicketStatus, TicketListItem[]>;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tickets', href: '/tickets' },
    { title: 'Quadro', href: '/tickets/quadro' },
];

/** Faixa de cor no topo de cada coluna — mesma linguagem das etiquetas de situação. */
const COLUMN_ACCENT: Record<TicketStatus, string> = {
    open: 'bg-primary',
    doing: 'bg-warning',
    resolved: 'bg-success',
    closed: 'bg-muted-foreground/40',
};

interface KanbanPageProps {
    columns: Columns;
    stats: TicketStats;
    clients: ClientOption[];
    agents: AgentOption[];
}

export default function TicketsKanban({ columns, clients, agents }: KanbanPageProps) {
    const [board, setBoard] = useState<Columns>(columns);
    const [creating, setCreating] = useState(false);
    const [over, setOver] = useState<TicketStatus | null>(null);
    const dragged = useRef<{ id: number; from: TicketStatus } | null>(null);

    // O servidor manda a verdade a cada visita; o estado local só existe para o
    // arrasto responder na hora.
    useEffect(() => setBoard(columns), [columns]);

    function drop(to: TicketStatus) {
        const item = dragged.current;
        dragged.current = null;
        setOver(null);
        if (!item || item.from === to) return;

        // Move na tela já; confirma no servidor; se falhar, recarrega a verdade.
        setBoard((prev) => {
            const card = prev[item.from].find((t) => t.id === item.id);
            if (!card) return prev;
            return {
                ...prev,
                [item.from]: prev[item.from].filter((t) => t.id !== item.id),
                [to]: [{ ...card, status: to }, ...prev[to]],
            };
        });

        router.put(route('tickets.update', item.id), { status: to }, { preserveScroll: true, preserveState: true, onError: () => router.reload() });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tickets · Quadro" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Quadro de tickets</h1>
                        <p className="text-muted-foreground text-sm">Arraste um card entre as colunas para mudar a situação.</p>
                    </div>

                    <div className="flex items-center gap-2">
                        <TicketsViewToggle current="board" />
                        <Button onClick={() => setCreating(true)}>
                            <Plus />
                            Novo ticket
                        </Button>
                    </div>
                </div>

                <div className="flex min-w-0 flex-1 gap-4 overflow-x-auto pb-2">
                    {ticketStatusOrder.map((status) => (
                        <div
                            key={status}
                            onDragOver={(e) => {
                                e.preventDefault();
                                setOver(status);
                            }}
                            onDragLeave={(e) => {
                                // Só apaga o realce ao sair de verdade da coluna, não ao passar sobre um filho.
                                if (!e.currentTarget.contains(e.relatedTarget as Node)) setOver((c) => (c === status ? null : c));
                            }}
                            onDrop={() => drop(status)}
                            className={cn(
                                'bg-muted/40 flex w-72 shrink-0 flex-col rounded-xl border transition-colors',
                                over === status ? 'border-primary/50 bg-primary/5' : 'border-transparent',
                            )}
                        >
                            <div className="flex items-center gap-2 px-3 pt-3 pb-2">
                                <span className={cn('size-2 rounded-full', COLUMN_ACCENT[status])} />
                                <span className="text-sm font-semibold">{ticketStatusLabels[status]}</span>
                                <span className="text-muted-foreground bg-background ml-auto rounded-full px-2 py-0.5 text-xs font-medium tabular">
                                    {board[status]?.length ?? 0}
                                </span>
                            </div>

                            <div className="flex min-h-24 flex-1 flex-col gap-2 p-2">
                                {(board[status] ?? []).map((ticket) => (
                                    <KanbanCard
                                        key={ticket.id}
                                        ticket={ticket}
                                        onDragStart={() => (dragged.current = { id: ticket.id, from: status })}
                                    />
                                ))}
                                {(board[status]?.length ?? 0) === 0 && (
                                    <p className="text-muted-foreground/60 px-2 py-6 text-center text-xs">Nada aqui.</p>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <TicketFormSheet open={creating} onOpenChange={setCreating} clients={clients} agents={agents} />
        </AppLayout>
    );
}

function KanbanCard({ ticket, onDragStart }: { ticket: TicketListItem; onDragStart: () => void }) {
    return (
        <div
            draggable
            onDragStart={onDragStart}
            onClick={() => router.visit(route('tickets.show', ticket.id))}
            className="bg-background hover:border-primary/40 group cursor-pointer rounded-lg border p-3 shadow-sm transition-colors active:cursor-grabbing"
        >
            <div className="flex items-start justify-between gap-2">
                <span className="text-muted-foreground tabular text-xs">{ticket.number}</span>
                <TicketPriorityBadge priority={ticket.priority} />
            </div>

            <p className="mt-1 line-clamp-2 text-sm font-medium">{ticket.subject}</p>

            <div className="text-muted-foreground mt-2.5 flex items-center gap-2 text-xs">
                {ticket.client ? (
                    <span className="flex min-w-0 items-center gap-1.5">
                        <ClientAvatar name={ticket.client.name} photoUrl={ticket.client.photo_url} className="size-5" />
                        <span className="truncate">{ticket.client.name}</span>
                    </span>
                ) : (
                    <span>Sem cliente</span>
                )}
                <span className="ml-auto shrink-0 truncate">{ticket.assignee?.name ?? 'Sem resp.'}</span>
            </div>
        </div>
    );
}
