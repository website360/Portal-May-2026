import { Badge } from '@/components/ui/badge';
import type { MaintenanceStatus } from '@/types/maintenance';
import { AlertTriangle, CalendarClock, CircleCheck, PauseCircle } from 'lucide-react';

const tones: Record<MaintenanceStatus, { variant: 'destructive' | 'warning' | 'success' | 'muted'; icon: typeof CircleCheck }> = {
    late: { variant: 'destructive', icon: AlertTriangle },
    pending: { variant: 'warning', icon: CalendarClock },
    done: { variant: 'success', icon: CircleCheck },
    paused: { variant: 'muted', icon: PauseCircle },
};

/**
 * O tamanho do atraso diz mais que o rótulo: "3 meses sem manutenção" já é a
 * ordem do dia, "Atrasada" só diz que existe um problema.
 */
export function statusLabel(status: MaintenanceStatus, pendingMonths: number): string {
    if (status === 'paused') return 'Pausado';
    if (status === 'done') return 'Feita este mês';
    if (status === 'pending') return 'Pendente este mês';

    return `${pendingMonths} meses sem manutenção`;
}

export function MaintenanceStatusBadge({ status, pendingMonths }: { status: MaintenanceStatus; pendingMonths: number }) {
    const tone = tones[status] ?? tones.done;
    const Icon = tone.icon;

    return (
        <Badge variant={tone.variant}>
            <Icon />
            {statusLabel(status, pendingMonths)}
        </Badge>
    );
}
