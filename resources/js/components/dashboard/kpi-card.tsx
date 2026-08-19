import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import type { Kpi } from '@/types/dashboard';
import { Briefcase, ListChecks, Minus, TrendingDown, TrendingUp, Users, Wallet, type LucideIcon } from 'lucide-react';

const icons: Record<string, LucideIcon> = {
    clients: Users,
    projects: Briefcase,
    revenue: Wallet,
    tasks: ListChecks,
};

export function KpiCard({ kpi }: { kpi: Kpi }) {
    const Icon = icons[kpi.key] ?? Wallet;
    const value = kpi.format === 'currency' ? formatCurrency(kpi.value) : formatNumber(kpi.value);

    return (
        <Card className="h-full transition-shadow hover:shadow-md">
            {/* Layout do Dashdark X: ícone + rótulo no topo, valor grande com o
                badge de variação ao lado. Alturas iguais nos quatro cards. */}
            <CardContent className="flex h-full flex-col gap-4 p-5">
                <div className="flex items-center gap-2.5">
                    <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-lg">
                        <Icon className="size-4.5" />
                    </span>
                    <span className="text-muted-foreground text-sm font-medium">{kpi.label}</span>
                </div>

                <div className="mt-auto flex flex-wrap items-baseline gap-x-2.5 gap-y-1.5">
                    <span className="tabular text-2xl font-bold tracking-tight">{value}</span>
                    <DeltaBadge delta={kpi.delta} goodWhen={kpi.goodWhen} />
                </div>
            </CardContent>
        </Card>
    );
}

function DeltaBadge({ delta, goodWhen }: { delta: number | null; goodWhen: Kpi['goodWhen'] }) {
    if (delta === null) {
        return <span className="text-muted-foreground text-xs">sem base</span>;
    }

    if (delta === 0) {
        return (
            <Badge variant="muted">
                <Minus />
                estável
            </Badge>
        );
    }

    const rising = delta > 0;
    const isGood = goodWhen === 'up' ? rising : !rising;

    return (
        <Badge variant={isGood ? 'success' : 'destructive'}>
            {rising ? <TrendingUp /> : <TrendingDown />}
            {formatPercent(delta)}
        </Badge>
    );
}
