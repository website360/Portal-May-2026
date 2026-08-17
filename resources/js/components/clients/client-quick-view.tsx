import { ClientAvatar } from '@/components/clients/client-avatar';
import { ClientStatusBadge, ClientTypeBadge, DetailGroup, DetailItem, formatAddress } from '@/components/clients/client-details';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { formatCurrency } from '@/lib/format';
import type { Client } from '@/types/clients';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, Pencil } from 'lucide-react';

interface ClientQuickViewProps {
    client: Client | null;
    onOpenChange: (open: boolean) => void;
    onEdit: (client: Client) => void;
}

/**
 * Prévia sem sair da lista. Não busca nada: a listagem já traz todos os campos
 * do cliente, então abrir é instantâneo. Para o histórico de projetos e faturas,
 * o botão leva à página completa.
 */
export function ClientQuickView({ client, onOpenChange, onEdit }: ClientQuickViewProps) {
    return (
        <Sheet open={client !== null} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                {client && (
                    <>
                        <SheetHeader className="border-b p-6 text-left">
                            <div className="flex items-start gap-4 pr-8">
                                <ClientAvatar name={client.name} photoUrl={client.photo_url} className="size-14 text-base" />

                                <div className="min-w-0 space-y-1.5">
                                    <SheetTitle className="truncate">{client.name}</SheetTitle>
                                    <SheetDescription className="truncate">
                                        {client.trade_name || client.segment || 'Sem nome fantasia'}
                                    </SheetDescription>

                                    <div className="flex flex-wrap items-center gap-1.5 pt-0.5">
                                        <ClientStatusBadge status={client.status} />
                                        <ClientTypeBadge type={client.type} />
                                    </div>
                                </div>
                            </div>
                        </SheetHeader>

                        <div className="min-h-0 flex-1 space-y-6 overflow-y-auto p-6">
                            <DetailGroup title="Identificação">
                                <DetailItem label={client.type === 'person' ? 'CPF' : 'CNPJ'} value={client.document} />
                                <DetailItem label="Segmento" value={client.segment} />
                            </DetailGroup>

                            <Separator />

                            <DetailGroup title="Contato">
                                <DetailItem label="E-mail" value={client.email} wide />
                                <DetailItem label="Telefone" value={client.phone} />
                                <DetailItem label="Responsável" value={client.contact_name} />
                                <DetailItem label="Cargo" value={client.contact_role} />
                            </DetailGroup>

                            <Separator />

                            <DetailGroup title="Endereço">
                                <DetailItem label="Endereço" value={formatAddress(client)} wide />
                                <DetailItem label="CEP" value={client.zip_code} />
                            </DetailGroup>

                            <Separator />

                            <DetailGroup title="Comercial">
                                <DetailItem
                                    label="Mensalidade"
                                    value={client.monthly_fee === null ? null : <span className="tabular">{formatCurrency(client.monthly_fee)}</span>}
                                />
                                <DetailItem label="Projetos" value={<span className="tabular">{client.projects_count}</span>} />
                                <DetailItem label="Observações" value={client.notes} wide />
                            </DetailGroup>
                        </div>

                        <div className="bg-muted/30 flex items-center justify-end gap-2 border-t p-6">
                            <Button variant="outline" onClick={() => onEdit(client)}>
                                <Pencil />
                                Editar
                            </Button>

                            <Button asChild>
                                <Link href={route('clientes.show', client.id)}>
                                    Abrir página completa
                                    <ArrowUpRight />
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
