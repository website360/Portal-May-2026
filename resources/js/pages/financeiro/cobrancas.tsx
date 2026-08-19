import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CircleCheck, MessageCircle, TriangleAlert } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Financeiro', href: '/financeiro' },
    { title: 'Histórico de cobranças', href: '/financeiro/cobrancas' },
];

interface ChargeLog {
    transaction_id: number;
    ok: boolean;
    message: string;
    at: string;
    client?: string;
    description?: string;
    amount?: string;
    channels?: string;
}

export default function Cobrancas({ history }: { history: ChargeLog[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Histórico de cobranças" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Histórico de cobranças</h1>
                        <p className="text-muted-foreground text-sm">Tudo que já foi enviado: para quem, quanto, por onde e o resultado.</p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href="/financeiro">
                            <ArrowLeft />
                            Voltar ao financeiro
                        </Link>
                    </Button>
                </div>

                {history.length === 0 ? (
                    <Card>
                        <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                <MessageCircle className="size-5" />
                            </span>
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Nenhuma cobrança enviada ainda</p>
                                <p className="text-muted-foreground text-sm">Use o botão "Cobrar" numa conta a receber em aberto no financeiro.</p>
                            </div>
                        </div>
                    </Card>
                ) : (
                    <Card className="overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-b text-left text-xs font-medium tracking-wide uppercase">
                                        <th className="py-2.5 pr-4 pl-6 font-medium">Quando</th>
                                        <th className="px-4 py-2.5 font-medium">Cliente</th>
                                        <th className="px-4 py-2.5 font-medium">Fatura</th>
                                        <th className="px-4 py-2.5 text-right font-medium">Valor</th>
                                        <th className="px-4 py-2.5 font-medium">Canal</th>
                                        <th className="py-2.5 pr-6 pl-4 font-medium">Resultado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.map((log, i) => (
                                        <tr key={i} className="border-b transition-colors last:border-b-0">
                                            <td className="tabular text-muted-foreground py-3 pr-4 pl-6 whitespace-nowrap">{log.at}</td>
                                            <td className="px-4 py-3">{log.client || '—'}</td>
                                            <td className="text-muted-foreground px-4 py-3">{log.description || '—'}</td>
                                            <td className="tabular px-4 py-3 text-right">{log.amount || '—'}</td>
                                            <td className="text-muted-foreground px-4 py-3">{log.channels || '—'}</td>
                                            <td className="py-3 pr-6 pl-4">
                                                {log.ok ? (
                                                    <Badge variant="success">
                                                        <CircleCheck />
                                                        Enviada
                                                    </Badge>
                                                ) : (
                                                    <span className="flex items-center gap-1.5">
                                                        <Badge variant="muted">Não enviada</Badge>
                                                        <span title={log.message}>
                                                            <TriangleAlert className="text-warning size-3.5" />
                                                        </span>
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
