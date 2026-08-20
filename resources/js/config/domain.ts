import type { BadgeProps } from '@/components/ui/badge';
import type { SegmentedOption } from '@/components/ui/segmented-control';
import type { StatusOption } from '@/components/ui/status-picker';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Building2,
    Circle,
    CircleAlert,
    CircleCheck,
    CircleDot,
    CircleSlash,
    Layers,
    RefreshCw,
    UserRound,
} from 'lucide-react';

type BadgeVariant = NonNullable<BadgeProps['variant']>;

interface StatusTone {
    label: string;
    variant: BadgeVariant;
    /** Barra lateral usada em linhas de tabela e cards de kanban. */
    border: string;
}

/**
 * Mapas de tom por status. Todo modulo novo registra os seus aqui, para que o
 * sistema inteiro fale a mesma lingua visual de "ok / atencao / erro / neutro".
 */
export const projectStatus: Record<string, StatusTone> = {
    in_progress: { label: 'Em andamento', variant: 'default', border: 'border-l-primary' },
    completed: { label: 'Concluído', variant: 'success', border: 'border-l-success' },
    late: { label: 'Atrasado', variant: 'destructive', border: 'border-l-destructive' },
};

export const clientStatus: Record<string, StatusTone> = {
    active: { label: 'Ativo', variant: 'success', border: 'border-l-success' },
    inactive: { label: 'Inativo', variant: 'muted', border: 'border-l-muted-foreground/40' },
};

/**
 * Situação do domínio, derivada do vencimento no servidor. "expiring" cobre a
 * janela de 30 dias antes de vencer.
 */
export const domainStatus: Record<string, StatusTone> = {
    expired: { label: 'Vencido', variant: 'destructive', border: 'border-l-destructive' },
    expiring: { label: 'Vence em breve', variant: 'warning', border: 'border-l-warning' },
    ok: { label: 'Em dia', variant: 'success', border: 'border-l-success' },
    unknown: { label: 'Sem data', variant: 'muted', border: 'border-l-muted-foreground/40' },
};

/** Quem renova: só os da agência entram nos avisos de vencimento. */
export const domainManagedBy: Record<string, StatusTone> = {
    agency: { label: 'Agência', variant: 'default', border: 'border-l-primary' },
    client: { label: 'Cliente', variant: 'muted', border: 'border-l-muted-foreground/40' },
};

export const taskStatus: Record<string, StatusTone> = {
    pending: { label: 'A fazer', variant: 'muted', border: 'border-l-muted-foreground/40' },
    doing: { label: 'Em andamento', variant: 'default', border: 'border-l-primary' },
    done: { label: 'Concluída', variant: 'success', border: 'border-l-success' },
};

export const taskPriority: Record<string, StatusTone> = {
    low: { label: 'Baixa', variant: 'muted', border: 'border-l-muted-foreground/40' },
    normal: { label: 'Normal', variant: 'secondary', border: 'border-l-muted-foreground/40' },
    high: { label: 'Alta', variant: 'warning', border: 'border-l-warning' },
    urgent: { label: 'Urgente', variant: 'destructive', border: 'border-l-destructive' },
};

/*
 * Opções dos seletores inline. Ficam aqui para que a mesma lista sirva a
 * qualquer listagem que mostre aquele campo.
 */

export const taskStatusOptions: StatusOption[] = [
    { value: 'pending', label: 'A fazer', variant: 'muted', icon: Circle },
    { value: 'doing', label: 'Em andamento', variant: 'default', icon: CircleDot },
    { value: 'done', label: 'Concluída', variant: 'success', icon: CircleCheck },
];

/** Mesmas situações, no formato do controle segmentado dos formulários. */
export const taskStatusSegments: SegmentedOption[] = [
    { value: 'pending', label: 'A fazer', icon: Circle },
    { value: 'doing', label: 'Em andamento', icon: CircleDot, activeClassName: 'text-primary' },
    { value: 'done', label: 'Concluída', icon: CircleCheck, activeClassName: 'text-success' },
];

export const taskPrioritySegments: SegmentedOption[] = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta', activeClassName: 'text-warning' },
    { value: 'urgent', label: 'Urgente', activeClassName: 'text-destructive' },
];

export const clientStatusOptions: StatusOption[] = [
    { value: 'active', label: 'Ativo', variant: 'success', icon: CircleCheck },
    { value: 'inactive', label: 'Inativo', variant: 'muted', icon: CircleSlash },
];

export const domainManagedByOptions: StatusOption[] = [
    { value: 'agency', label: 'Agência', variant: 'default', icon: Building2, hint: 'avisa vencimento' },
    { value: 'client', label: 'Cliente', variant: 'muted', icon: UserRound, hint: 'só registro' },
];

/*
 * Financeiro
 */

export const transactionType: Record<string, StatusTone> = {
    payable: { label: 'A pagar', variant: 'destructive', border: 'border-l-destructive' },
    receivable: { label: 'A receber', variant: 'success', border: 'border-l-success' },
};

export const transactionStatus: Record<string, StatusTone> = {
    pending: { label: 'Em aberto', variant: 'muted', border: 'border-l-muted-foreground/40' },
    overdue: { label: 'Vencida', variant: 'destructive', border: 'border-l-destructive' },
    paid: { label: 'Paga', variant: 'success', border: 'border-l-success' },
};

/**
 * "Vencida" aparece no badge mas não se escolhe: ela é consequência do
 * vencimento, não uma decisão. Dá para dar baixa ou estornar.
 */
export const transactionStatusOptions: StatusOption[] = [
    { value: 'pending', label: 'Em aberto', variant: 'muted', icon: Circle },
    { value: 'overdue', label: 'Vencida', variant: 'destructive', icon: CircleAlert, selectable: false },
    { value: 'paid', label: 'Paga', variant: 'success', icon: CircleCheck, hint: 'dá baixa hoje' },
];

export const transactionTypeSegments: SegmentedOption[] = [
    { value: 'payable', label: 'A pagar', icon: ArrowDownLeft, activeClassName: 'text-destructive' },
    { value: 'receivable', label: 'A receber', icon: ArrowUpRight, activeClassName: 'text-success' },
];

/**
 * Como o lançamento se repete.
 *
 * Parcelado e recorrente parecem a mesma coisa e nao sao: parcelado fatia uma
 * divida que ja existe inteira — as doze contas nascem hoje e ninguem renova
 * nada. Recorrente e um compromisso que se renova; so a proxima cobranca vira
 * conta, e o contrato precisa ser avisado antes de acabar.
 */
export const repeatSegments: SegmentedOption[] = [
    { value: 'once', label: 'Única', icon: Circle },
    { value: 'installments', label: 'Parcelada', icon: Layers },
    { value: 'recurring', label: 'Recorrente', icon: RefreshCw },
];

export const recurrenceIntervals = [
    { value: 'monthly', label: 'Mês' },
    { value: 'quarterly', label: 'Trimestre' },
    { value: 'semiannual', label: 'Semestre' },
    { value: 'annual', label: 'Ano' },
];

/**
 * Paleta fechada para centros de custo e categorias. Presa aos tokens do tema,
 * então cor escolhida aqui continua legível no claro e no escuro.
 */
export const paletteColors: Record<string, { label: string; dot: string; chip: string }> = {
    blue: { label: 'Azul', dot: 'bg-primary', chip: 'border-primary/20 bg-primary/10 text-primary' },
    green: { label: 'Verde', dot: 'bg-success', chip: 'border-success/20 bg-success/10 text-success' },
    amber: { label: 'Âmbar', dot: 'bg-warning', chip: 'border-warning/20 bg-warning/10 text-warning' },
    red: { label: 'Vermelho', dot: 'bg-destructive', chip: 'border-destructive/20 bg-destructive/10 text-destructive' },
    sky: { label: 'Celeste', dot: 'bg-chart-2', chip: 'border-chart-2/20 bg-chart-2/10 text-chart-2' },
    violet: { label: 'Violeta', dot: 'bg-chart-5', chip: 'border-chart-5/20 bg-chart-5/10 text-chart-5' },
    gray: { label: 'Cinza', dot: 'bg-muted-foreground', chip: 'border-muted-foreground/20 bg-muted-foreground/10 text-muted-foreground' },
};

export function colorOf(color: string | null | undefined) {
    return paletteColors[color ?? 'blue'] ?? paletteColors.blue;
}

const fallback: StatusTone = { label: '—', variant: 'muted', border: 'border-l-muted-foreground/40' };

export function toneFor(map: Record<string, StatusTone>, status: string): StatusTone {
    return map[status] ?? fallback;
}
