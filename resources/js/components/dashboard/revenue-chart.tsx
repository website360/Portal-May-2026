import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { formatCompactCurrency, formatCurrency } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { RevenuePoint } from '@/types/dashboard';
import { useState } from 'react';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type View = 'received' | 'paid' | 'both';

const viewOptions = [
    { value: 'received', label: 'Recebidas' },
    { value: 'paid', label: 'Pagas' },
    { value: 'both', label: 'Ambas' },
];

/** Cada série: a chave nos dados, o rótulo e a cor. */
const SERIES = {
    received: { key: 'received' as const, label: 'Recebidas', color: 'var(--primary)' },
    paid: { key: 'paid' as const, label: 'Pagas', color: 'var(--destructive)' },
};

interface TooltipState {
    active?: boolean;
    label?: string | number;
    payload?: readonly { name?: string; value?: number | string; color?: string; stroke?: string }[];
}

function ChartTooltip({ active, payload, label }: TooltipState) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="bg-popover rounded-lg border px-3 py-2 shadow-lg">
            <p className="text-muted-foreground text-xs">{label}</p>
            {payload.map((entry, index) => (
                <p key={index} className="tabular text-foreground flex items-center gap-2 text-sm font-semibold">
                    <span className="size-2 rounded-full" style={{ backgroundColor: entry.color ?? entry.stroke }} />
                    {payload.length > 1 && <span className="text-muted-foreground text-xs font-normal">{entry.name}</span>}
                    {formatCurrency(Number(entry.value ?? 0))}
                </p>
            ))}
        </div>
    );
}

export function RevenueChart({ data }: { data: RevenuePoint[] }) {
    const [view, setView] = useState<View>('received');

    const totalReceived = data.reduce((sum, point) => sum + point.received, 0);
    const totalPaid = data.reduce((sum, point) => sum + point.paid, 0);
    const balance = totalReceived - totalPaid;

    const showReceived = view === 'received' || view === 'both';
    const showPaid = view === 'paid' || view === 'both';

    const heading =
        view === 'received' ? 'Faturamento — últimos 12 meses' : view === 'paid' ? 'Contas pagas — últimos 12 meses' : 'Entradas e saídas — últimos 12 meses';

    return (
        <Card className="h-full overflow-hidden">
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="gap-1">
                    <span className="text-muted-foreground text-sm font-medium">{heading}</span>
                    {view === 'both' ? (
                        <div className="space-y-0.5">
                            <span className={cn('tabular block text-3xl font-bold tracking-tight', balance < 0 && 'text-destructive')}>
                                {formatCurrency(balance)}
                            </span>
                            <span className="text-muted-foreground text-xs">
                                Recebido {formatCurrency(totalReceived)} · Pago {formatCurrency(totalPaid)}
                            </span>
                        </div>
                    ) : (
                        <span className="tabular block text-3xl font-bold tracking-tight">
                            {formatCurrency(view === 'received' ? totalReceived : totalPaid)}
                        </span>
                    )}
                </div>

                <SegmentedControl value={view} onChange={(v) => setView(v as View)} options={viewOptions} aria-label="Filtrar o gráfico" />
            </CardHeader>

            <CardContent className="pl-0">
                <div className="h-[320px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={data} margin={{ top: 8, right: 16, bottom: 0, left: 8 }}>
                            <defs>
                                <linearGradient id="fill-received" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="var(--primary)" stopOpacity={0.45} />
                                    <stop offset="55%" stopColor="var(--primary)" stopOpacity={0.12} />
                                    <stop offset="100%" stopColor="var(--primary)" stopOpacity={0} />
                                </linearGradient>
                                <linearGradient id="fill-paid" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="var(--destructive)" stopOpacity={0.4} />
                                    <stop offset="55%" stopColor="var(--destructive)" stopOpacity={0.1} />
                                    <stop offset="100%" stopColor="var(--destructive)" stopOpacity={0} />
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
                                cursor={{ stroke: 'var(--muted-foreground)', strokeWidth: 1, strokeDasharray: '4 4' }}
                                content={(props) => <ChartTooltip {...(props as TooltipState)} />}
                            />

                            {showReceived && (
                                <Area
                                    type="monotone"
                                    name={SERIES.received.label}
                                    dataKey={SERIES.received.key}
                                    stroke={SERIES.received.color}
                                    strokeWidth={2.5}
                                    fill="url(#fill-received)"
                                    activeDot={{ r: 5, strokeWidth: 3, stroke: 'var(--background)', fill: SERIES.received.color }}
                                />
                            )}

                            {showPaid && (
                                <Area
                                    type="monotone"
                                    name={SERIES.paid.label}
                                    dataKey={SERIES.paid.key}
                                    stroke={SERIES.paid.color}
                                    strokeWidth={2.5}
                                    fill="url(#fill-paid)"
                                    activeDot={{ r: 5, strokeWidth: 3, stroke: 'var(--background)', fill: SERIES.paid.color }}
                                />
                            )}
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
