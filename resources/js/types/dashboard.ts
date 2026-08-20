import type { DomainAlert } from '@/components/dashboard/domain-alerts';

export type KpiFormat = 'number' | 'currency';

export interface Kpi {
    key: string;
    label: string;
    value: number;
    format: KpiFormat;
    /** Variacao percentual contra o mes anterior, ou null quando nao ha base. */
    delta: number | null;
    goodWhen: 'up' | 'down';
}

export interface RevenuePoint {
    month: string;
    label: string;
    revenue: number;
}

export interface RecentProject {
    id: number;
    name: string;
    client: string;
    status: string;
    budget: number;
    dueDate: string | null;
}

export interface ActivityItem {
    id: number;
    user: string;
    description: string;
    when: string;
}

export interface DashboardProps {
    kpis: Kpi[];
    revenueSeries: RevenuePoint[];
    recentProjects: RecentProject[];
    activities: ActivityItem[];
    domainAlerts: { total: number; items: DomainAlert[] };
    endingRecurrences: import('@/components/dashboard/ending-recurrences').EndingRecurrence[];
    priceReviews: import('@/components/dashboard/price-reviews').PriceReview[];
}
