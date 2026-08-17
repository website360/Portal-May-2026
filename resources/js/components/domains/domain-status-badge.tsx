import { Badge } from '@/components/ui/badge';
import { domainManagedBy, domainStatus, toneFor } from '@/config/domain';
import type { Domain } from '@/types/domains';
import { AlertTriangle, CalendarClock, CircleCheck, CircleHelp } from 'lucide-react';

const icons = {
    expired: AlertTriangle,
    expiring: CalendarClock,
    ok: CircleCheck,
    unknown: CircleHelp,
};

/** "Venceu há 9 dias" diz mais que "Vencido" — o prazo é a informação útil. */
export function expiryLabel(status: Domain['status'], daysLeft: number | null): string {
    if (status === 'unknown' || daysLeft === null) {
        return 'Sem data';
    }

    if (daysLeft < 0) {
        const days = Math.abs(daysLeft);

        return days === 1 ? 'Venceu ontem' : `Venceu há ${days} dias`;
    }

    if (daysLeft === 0) return 'Vence hoje';
    if (daysLeft === 1) return 'Vence amanhã';
    if (status === 'expiring') return `Vence em ${daysLeft} dias`;

    return 'Em dia';
}

export function DomainStatusBadge({ status, daysLeft }: { status: Domain['status']; daysLeft: number | null }) {
    const tone = toneFor(domainStatus, status);
    const Icon = icons[status] ?? CircleHelp;

    return (
        <Badge variant={tone.variant}>
            <Icon />
            {expiryLabel(status, daysLeft)}
        </Badge>
    );
}

export function DomainManagedByBadge({ managedBy }: { managedBy: Domain['managed_by'] }) {
    const tone = toneFor(domainManagedBy, managedBy);

    return <Badge variant={tone.variant}>{tone.label}</Badge>;
}
