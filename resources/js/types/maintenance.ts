import type { ClientOption } from '@/types/domains';

export type MaintenanceResult = 'done' | 'not_needed' | 'skipped';

export type MaintenanceStatus = 'done' | 'pending' | 'late' | 'paused';

export type MaintenanceTab = 'planos' | 'historico';

export interface ChecklistItem {
    key: string;
    label: string;
}

/** O item já preenchido, como ficou gravado na manutenção. */
export interface ChecklistEntry extends ChecklistItem {
    result: MaintenanceResult;
}

export interface Checklist {
    items: ChecklistItem[];
    results: { value: MaintenanceResult; label: string }[];
}

export interface MaintenancePlan {
    id: number;
    client_id: number;
    site_url: string;
    last_performed_at: string | null;
    last_performed_label: string | null;
    /** O mês da última manutenção por extenso: "julho de 2026". */
    last_month_label: string | null;
    /** Meses devendo manutenção, contando o corrente. Zero = feita este mês. */
    pending_months: number;
    status: MaintenanceStatus;
    active: boolean;
    notes: string | null;
    client: { id: number; name: string; photo_url: string | null; has_phone: boolean };
}

export interface MaintenanceRecord {
    id: number;
    plan_id: number;
    performed_at: string;
    performed_label: string;
    items: ChecklistEntry[];
    done_count: number;
    skipped_count: number;
    total_count: number;
    notes: string | null;
    notified_at: string | null;
    notify_error: string | null;
    user: string | null;
    site_url: string;
    client: { id: number; name: string; photo_url: string | null };
}

export interface MaintenanceStats {
    active: number;
    late: number;
    pending: number;
    done: number;
}

export interface MaintenanceFilters {
    tab: MaintenanceTab;
    search: string;
    /** Situação do plano. Vazio = todas. */
    statuses: string[];
    /** IDs de cliente, como texto — é o que a URL carrega. */
    clients: string[];
    /** IDs de quem executou. */
    users: string[];
    /** 'sent' | 'not_sent'. */
    reports: string[];
    /** "AAAA-MM"; vazio = todos os períodos. */
    month: string;
    sort: string;
    direction: 'asc' | 'desc';
}

/** Filtros que valem em cada aba — trocar de aba limpa os da outra. */
export const PLAN_FILTER_KEYS = ['statuses', 'clients'] as const;

export const HISTORY_FILTER_KEYS = ['clients', 'users', 'reports', 'month'] as const;

export interface PlanFormData {
    client_id: string;
    site_url: string;
    active: boolean;
    notes: string;

    [key: string]: string | boolean;
}

export const EMPTY_PLAN_FORM: PlanFormData = {
    client_id: '',
    site_url: '',
    active: true,
    notes: '',
};

export function toPlanFormData(plan: MaintenancePlan): PlanFormData {
    return {
        client_id: String(plan.client_id),
        site_url: plan.site_url,
        active: plan.active,
        notes: plan.notes ?? '',
    };
}

export type { ClientOption };
