import { DomainAlerts } from '@/components/dashboard/domain-alerts';
import { EndingRecurrences } from '@/components/dashboard/ending-recurrences';
import { KpiCard } from '@/components/dashboard/kpi-card';
import { RecentActivity } from '@/components/dashboard/recent-activity';
import { RecentProjects } from '@/components/dashboard/recent-projects';
import { RevenueChart } from '@/components/dashboard/revenue-chart';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { DashboardProps } from '@/types/dashboard';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

export default function Dashboard({ kpis, revenueSeries, recentProjects, activities, domainAlerts, endingRecurrences }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="animate-fade-in flex flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Bem-vindo de volta, {firstName}</h1>
                        <p className="text-muted-foreground text-sm">Como a agência está performando neste mês.</p>
                    </div>

                    <Button asChild>
                        <Link href="/financeiro">
                            Ver financeiro
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {kpis.map((kpi) => (
                        <KpiCard key={kpi.key} kpi={kpi} />
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <RevenueChart data={revenueSeries} />
                    </div>

                    <RecentActivity items={activities} />
                </div>

                <RecentProjects projects={recentProjects} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <DomainAlerts alerts={domainAlerts} />
                    <EndingRecurrences recurrences={endingRecurrences} />
                </div>
            </div>
        </AppLayout>
    );
}
