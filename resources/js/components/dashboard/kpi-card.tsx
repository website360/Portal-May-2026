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
        <Card className="h-full hover:shadow-md">
            {/* Valor e rodape em linhas proprias: os quatro cards ficam sempre da mesma altura,
                mesmo quando o valor e longo (faturamento) ou curto (contagens). */}
            <CardContent className="flex h-full flex-col gap-3 p-5">
                <div className="flex items-start justify-between gap-3">
                    <span className="text-muted-foreground text-sm font-medium">{kpi.label}</span>
                    <span className="bg-accent text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
                        <Icon className="size-4.5" />
                    </span>
                </div>

                <span className="tabular text-2xl font-bold tracking-tight">{value}</span>

                <div className="mt-auto flex flex-wrap items-center gap-x-2 gap-y-1.5 pt-1">
                    <DeltaBadge delta={kpi.delta} goodWhen={kpi.goodWhen} />
                    <span className="text-muted-foreground text-xs">{kpi.delta === null ? 'Sem base de comparação' : 'vs. mês anterior'}</span>
                </div>
            </CardContent>
        </Card>
    );
}

function DeltaBadge({ delta, goodWhen }: { delta: number | null; goodWhen: Kpi['goodWhen'] }) {
    if (delta === null) {
        return null;
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
