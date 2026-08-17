import { Badge } from '@/components/ui/badge';
import type { ContractStatus } from '@/types/contracts';
import { AlertTriangle, Ban, CalendarClock, CircleCheck, FileClock } from 'lucide-react';

const tones: Record<ContractStatus, { variant: 'destructive' | 'warning' | 'success' | 'muted'; icon: typeof CircleCheck }> = {
    draft: { variant: 'warning', icon: FileClock },
    active: { variant: 'success', icon: CircleCheck },
    expiring: { variant: 'warning', icon: CalendarClock },
    ended: { variant: 'muted', icon: AlertTriangle },
    cancelled: { variant: 'destructive', icon: Ban },
};

/** O prazo diz mais que o rótulo: "Vence em 12 dias" já é a ordem do dia. */
export function contractStatusLabel(status: ContractStatus, daysLeft: number | null): string {
    switch (status) {
        case 'draft':
            return 'Aguardando assinatura';
        case 'cancelled':
            return 'Cancelado';
        case 'ended':
            return 'Encerrado';
        case 'expiring':
            if (daysLeft === null) return 'Vencendo';
            if (daysLeft === 0) return 'Vence hoje';
            if (daysLeft === 1) return 'Vence amanhã';
            return `Vence em ${daysLeft} dias`;
        default:
            return daysLeft === null ? 'Vigente · sem prazo' : 'Vigente';
    }
}

export function ContractStatusBadge({ status, daysLeft }: { status: ContractStatus; daysLeft: number | null }) {
    const tone = tones[status] ?? tones.active;
    const Icon = tone.icon;

    return (
        <Badge variant={tone.variant}>
            <Icon />
            {contractStatusLabel(status, daysLeft)}
        </Badge>
    );
}
