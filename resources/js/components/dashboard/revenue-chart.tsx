import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { formatCompactCurrency, formatCurrency } from '@/lib/format';
import type { RevenuePoint } from '@/types/dashboard';
import { useState } from 'react';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type View = 'total' | 'paid' | 'pending' | 'overdue';

/** Cada visão: a chave nos dados, o rótulo, a legenda do total e a cor. */
const VIEWS: Record<View, { key: keyof RevenuePoint; label: string; caption: string; color: string; fill: string }> = {
    total: { key: 'total', label: 'A receber', caption: 'Total previsto', color: 'var(--primary)', fill: 'fill-total' },
    paid: { key: 'paid', label: 'Recebido', caption: 'Já entrou', color: 'var(--success)', fill: 'fill-paid' },
    pending: { key: 'pending', label: 'A vencer', caption: 'Em aberto, no prazo', color: 'var(--warning)', fill: 'fill-pending' },
    overdue: { key: 'overdue', label: 'Atrasado', caption: 'Venceu sem entrar', color: 'var(--destructive)', fill: 'fill-overdue' },
};

const viewOptions = (Object.keys(VIEWS) as View[]).map((v) => ({ value: v, label: VIEWS[v].label }));

interface TooltipState {
    active?: boolean;
    label?: string | number;
    payload?: readonly { value?: number | string; color?: string; stroke?: string }[];
}

function ChartTooltip({ active, payload, label }: TooltipState) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="bg-popover rounded-lg border px-3 py-2 shadow-lg">
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="tabular text-foreground flex items-center gap-2 text-sm font-semibold">
                <span className="size-2 rounded-full" style={{ backgroundColor: payload[0].color ?? payload[0].stroke }} />
                {formatCurrency(Number(payload[0].value ?? 0))}
            </p>
        </div>
    );
}

export function RevenueChart({ data }: { data: RevenuePoint[] }) {
    const [view, setView] = useState<View>('total');
    const active = VIEWS[view];

    const total = data.reduce((sum, point) => sum + (point[active.key] as number), 0);

    return (
        <Card className="h-full overflow-hidden">
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                <div className="gap-1">
                    <span className="text-muted-foreground text-sm font-medium">A receber — por vencimento</span>
                    <span className="tabular block text-3xl font-bold tracking-tight" style={{ color: active.color }}>
                        {formatCurrency(total)}
                    </span>
                    <span className="text-muted-foreground text-xs">{active.caption} · 12 meses</span>
                </div>

                <SegmentedControl value={view} onChange={(v) => setView(v as View)} options={viewOptions} aria-label="Filtrar o gráfico" />
            </CardHeader>

            <CardContent className="pl-0">
                <div className="h-[320px] w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={data} margin={{ top: 8, right: 16, bottom: 0, left: 8 }}>
                            <defs>
                                {(Object.keys(VIEWS) as View[]).map((v) => (
                                    <linearGradient key={v} id={VIEWS[v].fill} x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor={VIEWS[v].color} stopOpacity={0.45} />
                                        <stop offset="55%" stopColor={VIEWS[v].color} stopOpacity={0.12} />
                                        <stop offset="100%" stopColor={VIEWS[v].color} stopOpacity={0} />
                                    </linearGradient>
                                ))}
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
                                cursor={{ stroke: active.color, strokeWidth: 1, strokeDasharray: '4 4' }}
                                content={(props) => <ChartTooltip {...(props as TooltipState)} />}
                            />

                            <Area
                                type="monotone"
                                dataKey={active.key}
                                stroke={active.color}
                                strokeWidth={2.5}
                                fill={`url(#${active.fill})`}
                                activeDot={{ r: 5, strokeWidth: 3, stroke: 'var(--background)', fill: active.color }}
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </CardContent>
        </Card>
    );
}
