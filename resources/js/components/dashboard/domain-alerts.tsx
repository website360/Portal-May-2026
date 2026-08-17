import { expiryLabel } from '@/components/domains/domain-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowUpRight, CalendarClock } from 'lucide-react';

export interface DomainAlert {
    id: number;
    name: string;
    client: string;
    clientId: number;
    expiresAt: string | null;
    daysLeft: number | null;
    status: 'expired' | 'expiring' | 'ok' | 'unknown';
}

/**
 * Só aparece quando há domínio da agência vencido ou a vencer — um card fixo
 * dizendo "está tudo bem" viraria ruído que ninguém mais lê.
 */
export function DomainAlerts({ alerts }: { alerts: { total: number; items: DomainAlert[] } }) {
    if (alerts.total === 0) {
        return null;
    }

    const expired = alerts.items.filter((alert) => alert.status === 'expired').length;

    return (
        <Card className="border-warning/40">
            <CardHeader className="flex-row items-start justify-between space-y-0">
                <div className="flex items-start gap-3">
                    <span
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            expired > 0 ? 'bg-destructive/10 text-destructive' : 'bg-warning/10 text-warning',
                        )}
                    >
                        {expired > 0 ? <AlertTriangle className="size-4.5" /> : <CalendarClock className="size-4.5" />}
                    </span>

                    <div className="space-y-1.5">
                        <CardTitle>Domínios a renovar</CardTitle>
                        <CardDescription>
                            {alerts.total === 1 ? '1 domínio sob nossa gestão precisa' : `${alerts.total} domínios sob nossa gestão precisam`} de
                            atenção
                        </CardDescription>
                    </div>
                </div>

                <Button variant="outline" size="sm" asChild>
                    <Link href={route('dominios.index', { managed_by: 'agency', status: 'expiring' })}>
                        Ver todos
                        <ArrowUpRight />
                    </Link>
                </Button>
            </CardHeader>

            <CardContent className="pt-0">
                <ul className="divide-y">
                    {alerts.items.map((alert) => (
                        <li key={alert.id} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2.5 first:pt-0 last:pb-0">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium">{alert.name}</p>
                                <Link
                                    href={route('clientes.show', alert.clientId)}
                                    className="text-muted-foreground hover:text-primary truncate text-xs"
                                >
                                    {alert.client}
                                </Link>
                            </div>

                            <div className="text-right">
                                <p className={cn('text-sm font-medium', alert.status === 'expired' ? 'text-destructive' : 'text-warning')}>
                                    {expiryLabel(alert.status, alert.daysLeft)}
                                </p>
                                {alert.expiresAt && <p className="tabular text-muted-foreground text-xs">{alert.expiresAt}</p>}
                            </div>
                        </li>
                    ))}
                </ul>

                {alerts.total > alerts.items.length && (
                    <p className="text-muted-foreground mt-3 border-t pt-3 text-xs">
                        e mais {alerts.total - alerts.items.length} {alerts.total - alerts.items.length === 1 ? 'domínio' : 'domínios'}.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
