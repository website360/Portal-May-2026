import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { formatCompactCurrency, formatCurrency } from '@/lib/format';
import type { RevenuePoint } from '@/types/dashboard';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface TooltipState {
    active?: boolean;
    label?: string | number;
    payload?: readonly { value?: number | string }[];
}

function ChartTooltip({ active, payload, label }: TooltipState) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="bg-popover rounded-lg border px-3 py-2 shadow-lg">
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="tabular text-foreground text-sm font-semibold">{formatCurrency(Number(payload[0].value ?? 0))}</p>
        </div>
    );
}

export function RevenueChart({ data }: { data: RevenuePoint[] }) {
    const total = data.reduce((sum, point) => sum + point.revenue, 0);

    return (
        <Card className="overflow-hidden">
            <CardHeader className="gap-1">
                <span className="text-muted-foreground text-sm font-medium">Faturamento — últimos 12 meses</span>
                <span className="tabular text-3xl font-bold tracking-tight">{formatCurrency(total)}</span>
            </CardHeader>

            <CardContent className="pl-0">
                <div className="h-[320px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={data} margin={{ top: 8, right: 16, bottom: 0, left: 8 }}>
                            <defs>
                                <linearGradient id="revenue-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="var(--primary)" stopOpacity={0.45} />
                                    <stop offset="55%" stopColor="var(--primary)" stopOpacity={0.12} />
                                    <stop offset="100%" stopColor="var(--primary)" stopOpacity={0} />
                                </linearGradient>
                                <linearGradient id="revenue-stroke" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stopColor="var(--primary)" />
                                    <stop offset="100%" stopColor="var(--primary)" />
                                </linearGradient>
                            </defs>

                            <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />

                            <XAxis dataKey="label" tickLine={false} axisLine={false} stroke="var(--muted-foreground)" fontSize={12} tickMargin={10} />

                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                stroke="var(--muted-foreground)"
                                fontSize={12}
                                width={104}
                                tickFormatter={(value: number) => formatCompactCurrency(value)}
                            />

                            <Tooltip
                                cursor={{ stroke: 'var(--primary)', strokeWidth: 1, strokeDasharray: '4 4' }}
                                content={(props) => <ChartTooltip {...(props as TooltipState)} />}
                            />

                            <Area
                                type="monotone"
                                dataKey="revenue"
                                stroke="url(#revenue-stroke)"
                                strokeWidth={2.5}
                                fill="url(#revenue-fill)"
                                activeDot={{ r: 5, strokeWidth: 3, stroke: 'var(--background)', fill: 'var(--primary)' }}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
