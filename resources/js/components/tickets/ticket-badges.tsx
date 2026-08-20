import { cn } from '@/lib/utils';
import { ticketPriorityLabels, ticketStatusLabels, type TicketPriority, type TicketStatus } from '@/types/tickets';

const STATUS_TONE: Record<TicketStatus, string> = {
    open: 'bg-primary/10 text-primary',
    doing: 'bg-warning/15 text-warning',
    resolved: 'bg-success/10 text-success',
    closed: 'bg-muted text-muted-foreground',
};

const PRIORITY_TONE: Record<TicketPriority, string> = {
    low: 'bg-muted text-muted-foreground',
    normal: 'bg-primary/10 text-primary',
    high: 'bg-warning/15 text-warning',
    urgent: 'bg-destructive/10 text-destructive',
};

export function TicketStatusBadge({ status }: { status: TicketStatus }) {
    return (
        <span className={cn('inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium', STATUS_TONE[status])}>
            {ticketStatusLabels[status]}
        </span>
    );
}

export function TicketPriorityBadge({ priority }: { priority: TicketPriority }) {
    return (
        <span className={cn('inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium', PRIORITY_TONE[priority])}>
            {ticketPriorityLabels[priority]}
        </span>
    );
}
