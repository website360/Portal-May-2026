import { DomainAlerts } from '@/components/dashboard/domain-alerts';
import { EndingRecurrences } from '@/components/dashboard/ending-recurrences';
import { KpiCard } from '@/components/dashboard/kpi-card';
import { RecentActivity } from '@/components/dashboard/recent-activity';
import { RecentProjects } from '@/components/dashboard/recent-projects';
import { RevenueChart } from '@/components/dashboard/revenue-chart';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { DashboardProps } from '@/types/dashboard';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

export default function Dashboard({ kpis, revenueSeries, recentProjects, activities, domainAlerts, endingRecurrences }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="animate-fade-in flex flex-1 flex-col gap-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Visão geral</h1>
                    <p className="text-muted-foreground text-sm">Como a agência está performando neste mês.</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {kpis.map((kpi) => (
                        <KpiCard key={kpi.key} kpi={kpi} />
                    ))}
                </div>

                <DomainAlerts alerts={domainAlerts} />

                <EndingRecurrences recurrences={endingRecurrences} />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <RevenueChart data={revenueSeries} />
                    </div>

                    <RecentActivity items={activities} />
                </div>

                <RecentProjects projects={recentProjects} />
            </div>
        </AppLayout>
    );
}
