import { ClientAvatar } from '@/components/clients/client-avatar';
import { ClientStatusBadge, ClientTypeBadge, DetailGroup, DetailItem, formatAddress } from '@/components/clients/client-details';
import { ClientFormSheet } from '@/components/clients/client-form-sheet';
import { DeleteClientDialog } from '@/components/clients/delete-client-dialog';
import { DomainFormSheet } from '@/components/domains/domain-form-sheet';
import { DomainManagedByBadge, DomainStatusBadge } from '@/components/domains/domain-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { projectStatus, toneFor } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatNumber } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import type { Client } from '@/types/clients';
import type { Domain as DomainType } from '@/types/domains';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Briefcase, CircleDollarSign, Clock, Pencil, Plus, Trash2, Wallet } from 'lucide-react';
import { useState } from 'react';

interface ProjectRow {
    id: number;
    name: string;
    status: string;
    budget: number;
    dueDate: string | null;
}

interface InvoiceRow {
    id: number;
    amount: number;
    issuedAt: string | null;
    paidAt: string | null;
}

interface ShowPageProps {
    client: Client;
    summary: { projects: number; openProjects: number; billed: number; pending: number };
    projects: ProjectRow[];
    invoices: InvoiceRow[];
    domains: DomainType[];
}

export default function ShowClient({ client, summary, projects, invoices, domains }: ShowPageProps) {
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState<Client | null>(null);
    const [addingDomain, setAddingDomain] = useState(false);
    const [editingDomain, setEditingDomain] = useState<DomainType | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Clientes', href: '/clientes' },
        { title: client.name, href: route('clientes.show', client.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={client.name} />

            <div className="animate-fade-in flex flex-1 flex-col gap-6 p-6">
                <Button variant="ghost" size="sm" asChild className="text-muted-foreground -ml-2 self-start">
                    <Link href={route('clientes.index')}>
                        <ArrowLeft />
                        Voltar para clientes
                    </Link>
                </Button>

                <Card>
                    <CardContent className="flex flex-wrap items-start justify-between gap-6 p-6">
                        <div className="flex min-w-0 items-start gap-4">
                            <ClientAvatar name={client.name} photoUrl={client.photo_url} className="size-16 text-lg" />

                            <div className="min-w-0 space-y-2">
                                <h1 className="truncate text-2xl font-bold tracking-tight">{client.name}</h1>

                                <p className="text-muted-foreground text-sm">
                                    {[client.trade_name, client.document].filter(Boolean).join(' · ') || 'Sem nome fantasia ou documento'}
                                </p>

                                <div className="flex flex-wrap items-center gap-1.5">
                                    <ClientStatusBadge status={client.status} />
                                    <ClientTypeBadge type={client.type} />
                                    {client.segment && <Badge variant="secondary">{client.segment}</Badge>}
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button variant="outline" onClick={() => setEditing(true)}>
                                <Pencil />
                                Editar
                            </Button>

                            <Button variant="ghost" className="text-destructive hover:text-destructive" onClick={() => setDeleting(client)}>
                                <Trash2 />
                                Excluir
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat icon={Briefcase} label="Projetos" value={formatNumber(summary.projects)} hint={`${summary.openProjects} em andamento`} />
                    <Stat icon={Wallet} label="Mensalidade" value={client.monthly_fee === null ? '—' : formatCurrency(client.monthly_fee)} />
                    <Stat icon={CircleDollarSign} label="Faturado" value={formatCurrency(summary.billed)} hint="Todo o histórico" />
                    <Stat icon={Clock} label="A receber" value={formatCurrency(summary.pending)} hint="Faturas em aberto" />
                </div>

                <Card>
                    <CardContent className="grid gap-6 p-6 lg:grid-cols-2">
                        <DetailGroup title="Contato">
                            <DetailItem label="E-mail" value={client.email} wide />
                            <DetailItem label="Telefone" value={client.phone} />
                            <DetailItem label="Responsável" value={client.contact_name} />
                            <DetailItem label="Cargo" value={client.contact_role} />
                        </DetailGroup>

                        <DetailGroup title="Endereço">
                            <DetailItem label="Endereço" value={formatAddress(client)} wide />
                            <DetailItem label="CEP" value={client.zip_code} />
                            <DetailItem label="Cidade" value={[client.city, client.state].filter(Boolean).join('/') || null} />
                        </DetailGroup>

                        <DetailGroup title="Comercial" className="lg:col-span-2">
                            <DetailItem label="Segmento" value={client.segment} />
                            <DetailItem
                                label="Cliente desde"
                                value={client.started_at ? new Date(`${client.started_at}T00:00:00`).toLocaleDateString('pt-BR') : null}
                            />
                            <DetailItem label="Observações" value={client.notes} wide />
                        </DetailGroup>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Projetos</CardTitle>
                        <CardDescription>Tudo que a agência já entregou ou está entregando para este cliente</CardDescription>
                    </CardHeader>

                    {projects.length === 0 ? (
                        <p className="text-muted-foreground px-6 pb-6 text-sm">Nenhum projeto para este cliente ainda.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                        <th className="py-2.5 pr-4 pl-6 font-medium">Projeto</th>
                                        <th className="px-4 py-2.5 font-medium">Prazo</th>
                                        <th className="px-4 py-2.5 text-right font-medium">Valor</th>
                                        <th className="py-2.5 pr-6 pl-4 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {projects.map((project) => {
                                        const tone = toneFor(projectStatus, project.status);

                                        return (
                                            <tr key={project.id} className="hover:bg-muted/40 border-b transition-colors last:border-b-0">
                                                <td className="py-3 pr-4 pl-6 font-medium">{project.name}</td>
                                                <td className="tabular text-muted-foreground px-4 py-3">{project.dueDate ?? '—'}</td>
                                                <td className="tabular px-4 py-3 text-right">{formatCurrency(project.budget)}</td>
                                                <td className="py-3 pr-6 pl-4">
                                                    <Badge variant={tone.variant}>{tone.label}</Badge>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Card>
                    <CardHeader className="flex-row items-start justify-between space-y-0">
                        <div className="space-y-1.5">
                            <CardTitle>Domínios</CardTitle>
                            <CardDescription>Quem registra, quem renova e quando vence</CardDescription>
                        </div>

                        <Button variant="outline" size="sm" onClick={() => setAddingDomain(true)}>
                            <Plus />
                            Adicionar
                        </Button>
                    </CardHeader>

                    {domains.length === 0 ? (
                        <p className="text-muted-foreground px-6 pb-6 text-sm">Nenhum domínio vinculado a este cliente.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                        <th className="py-2.5 pr-4 pl-6 font-medium">Domínio</th>
                                        <th className="px-4 py-2.5 font-medium">Registrador</th>
                                        <th className="px-4 py-2.5 font-medium">Gestão</th>
                                        <th className="px-4 py-2.5 font-medium">Vencimento</th>
                                        <th className="px-4 py-2.5 text-right font-medium">Custo/ano</th>
                                        <th className="w-12 py-2.5 pr-6 pl-4" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {domains.map((domain) => (
                                        <tr key={domain.id} className="hover:bg-muted/40 border-b transition-colors last:border-b-0">
                                            <td className="py-3 pr-4 pl-6 font-medium">{domain.name}</td>
                                            <td className="text-muted-foreground px-4 py-3">{domain.registrar ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                <DomainManagedByBadge managedBy={domain.managed_by} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <DomainStatusBadge status={domain.status} daysLeft={domain.days_left} />
                                                {domain.expires_at_label && (
                                                    <div className="tabular text-muted-foreground mt-0.5 text-xs">{domain.expires_at_label}</div>
                                                )}
                                            </td>
                                            <td className="tabular px-4 py-3 text-right">
                                                {domain.annual_cost === null ? (
                                                    <span className="text-muted-foreground">—</span>
                                                ) : (
                                                    formatCurrency(domain.annual_cost)
                                                )}
                                            </td>
                                            <td className="py-3 pr-6 pl-4 text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Editar ${domain.name}`}
                                                    onClick={() => setEditingDomain(domain)}
                                                >
                                                    <Pencil />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Faturas recentes</CardTitle>
                        <CardDescription>As oito últimas emitidas</CardDescription>
                    </CardHeader>

                    {invoices.length === 0 ? (
                        <p className="text-muted-foreground px-6 pb-6 text-sm">Nenhuma fatura emitida para este cliente.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                        <th className="py-2.5 pr-4 pl-6 font-medium">Emissão</th>
                                        <th className="px-4 py-2.5 text-right font-medium">Valor</th>
                                        <th className="py-2.5 pr-6 pl-4 font-medium">Situação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoices.map((invoice) => (
                                        <tr key={invoice.id} className="hover:bg-muted/40 border-b transition-colors last:border-b-0">
                                            <td className="tabular text-muted-foreground py-3 pr-4 pl-6">{invoice.issuedAt ?? '—'}</td>
                                            <td className="tabular px-4 py-3 text-right">{formatCurrency(invoice.amount)}</td>
                                            <td className="py-3 pr-6 pl-4">
                                                {invoice.paidAt ? (
                                                    <Badge variant="success">Paga em {invoice.paidAt}</Badge>
                                                ) : (
                                                    <Badge variant="warning">Em aberto</Badge>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>

            <ClientFormSheet open={editing} onOpenChange={setEditing} client={client} />
            <DeleteClientDialog client={deleting} onOpenChange={() => setDeleting(null)} />

            {/* O cliente já está definido pelo contexto da página, então vem travado. */}
            <DomainFormSheet
                open={addingDomain || editingDomain !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAddingDomain(false);
                        setEditingDomain(null);
                    }
                }}
                domain={editingDomain}
                clients={[{ id: client.id, name: client.name }]}
                lockedClientId={client.id}
            />
        </AppLayout>
    );
}

function Stat({ icon: Icon, label, value, hint }: { icon: typeof Briefcase; label: string; value: string; hint?: string }) {
    return (
        <Card className="h-full">
            <CardContent className="flex h-full flex-col gap-3 p-5">
                <div className="flex items-start justify-between gap-3">
                    <span className="text-muted-foreground text-sm font-medium">{label}</span>
                    <span className="bg-accent text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
                        <Icon className="size-4.5" />
                    </span>
                </div>

                <span className="tabular text-2xl font-bold tracking-tight">{value}</span>

                {hint && <span className="text-muted-foreground mt-auto pt-1 text-xs">{hint}</span>}
            </CardContent>
        </Card>
    );
}
