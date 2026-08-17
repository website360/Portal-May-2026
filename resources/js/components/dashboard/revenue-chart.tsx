import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
        <div className="bg-popover rounded-lg border px-3 py-2 shadow-md">
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="tabular text-foreground text-sm font-semibold">{formatCurrency(Number(payload[0].value ?? 0))}</p>
        </div>
    );
}

export function RevenueChart({ data }: { data: RevenuePoint[] }) {
    const total = data.reduce((sum, point) => sum + point.revenue, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Faturamento</CardTitle>
                <CardDescription>
                    Últimos 12 meses · <span className="tabular">{formatCurrency(total)}</span> no período
                </CardDescription>
            </CardHeader>

            <CardContent className="pl-0">
                <div className="h-[320px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={data} margin={{ top: 8, right: 16, bottom: 0, left: 8 }}>
                            <defs>
                                <linearGradient id="revenue-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.35} />
                                    <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0} />
                                </linearGradient>
                            </defs>

                            <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />

                            <XAxis dataKey="label" tickLine={false} axisLine={false} stroke="var(--muted-foreground)" fontSize={12} tickMargin={10} />

                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                stroke="var(--muted-foreground)"
                                fontSize={12}
                                // Base 17px: menos que isso e o rotulo "R$ 200 mil" quebra em duas linhas.
                                width={104}
                                tickFormatter={(value: number) => formatCompactCurrency(value)}
                            />

                            <Tooltip
                                cursor={{ stroke: 'var(--border)', strokeWidth: 1 }}
                                content={(props) => <ChartTooltip {...(props as TooltipState)} />}
                            />

                            <Area
                                type="monotone"
                                dataKey="revenue"
                                stroke="var(--chart-1)"
                                strokeWidth={2}
                                fill="url(#revenue-fill)"
                                activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--background)' }}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
