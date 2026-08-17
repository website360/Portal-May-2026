import { ListSortSelect, type ListSorting } from '@/components/settings/list-sort-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteButton } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatNumber } from '@/lib/format';
import { maskCnpj, maskPhone } from '@/lib/masks';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Fornecedores', href: '/configuracoes/financeiro/fornecedores' },
];

export interface Supplier {
    id: number;
    name: string;
    trade_name: string | null;
    document: string | null;
    email: string | null;
    phone: string | null;
    active: boolean;
    transactions_count: number;
}

export default function Fornecedores({ suppliers, filters }: { suppliers: Supplier[]; filters: ListSorting }) {
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fornecedores" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">Fornecedores</h2>
                        <p className="text-muted-foreground text-sm">
                            Quem a agência paga. Cadastrar, em vez de digitar solto, é o que permite somar depois quanto foi para cada um.
                        </p>
                    </div>

                    <CreateForm />

                    <Card>
                        <CardHeader className="flex flex-wrap items-start justify-between gap-3">
                            <div className="space-y-1.5">
                                <CardTitle>Cadastrados</CardTitle>
                                <CardDescription>
                                    {suppliers.length === 0
                                        ? 'Nenhum fornecedor ainda.'
                                        : `${formatNumber(suppliers.length)} ${suppliers.length === 1 ? 'fornecedor' : 'fornecedores'}`}
                                </CardDescription>
                            </div>

                            <ListSortSelect
                                url={route('configuracoes.fornecedores.index')}
                                filters={filters}
                                options={[
                                    { value: 'name', label: 'Nome' },
                                    { value: 'usage', label: 'Lançamentos' },
                                    { value: 'status', label: 'Situação' },
                                ]}
                            />
                        </CardHeader>

                        <ul className="border-t">
                            {suppliers.map((supplier) =>
                                editingId === supplier.id ? (
                                    <EditRow key={supplier.id} supplier={supplier} onDone={() => setEditingId(null)} />
                                ) : (
                                    <ReadRow key={supplier.id} supplier={supplier} onEdit={() => setEditingId(supplier.id)} />
                                ),
                            )}
                        </ul>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function CreateForm() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        trade_name: '',
        document: '',
        email: '',
        phone: '',
        active: true,
    });

    return (
        <Card>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(route('configuracoes.fornecedores.store'), { preserveScroll: true, onSuccess: () => reset() });
                }}
                className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_10rem_auto] lg:items-end"
            >
                <div className="grid gap-2">
                    <Label htmlFor="name">Razão social</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Locaweb Serviços de Internet S.A."
                    />
                    {errors.name && <p className="text-destructive text-xs font-medium">{errors.name}</p>}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="trade_name">Nome fantasia</Label>
                    <Input id="trade_name" value={data.trade_name} onChange={(e) => setData('trade_name', e.target.value)} placeholder="Locaweb" />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="document">CNPJ</Label>
                    <Input id="document" inputMode="numeric" value={data.document} onChange={(e) => setData('document', maskCnpj(e.target.value))} />
                </div>

                <Button type="submit" loading={processing} disabled={data.name.trim() === ''}>
                    {!processing && <Plus />}
                    Adicionar
                </Button>
            </form>
        </Card>
    );
}

function ReadRow({ supplier, onEdit }: { supplier: Supplier; onEdit: () => void }) {
    const used = supplier.transactions_count ?? 0;

    return (
        <li className="hover:bg-muted/40 flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-6 py-3 transition-colors last:border-b-0">
            <div className="min-w-48 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm font-medium">{supplier.trade_name || supplier.name}</span>
                    {!supplier.active && <Badge variant="muted">Inativo</Badge>}
                </div>
                <p className="text-muted-foreground truncate text-xs">
                    {[supplier.trade_name ? supplier.name : null, supplier.document, supplier.email].filter(Boolean).join(' · ') || '—'}
                </p>
            </div>

            <span className="text-muted-foreground tabular text-xs">
                {used === 0 ? 'sem lançamentos' : `${formatNumber(used)} ${used === 1 ? 'lançamento' : 'lançamentos'}`}
            </span>

            <div className="flex items-center gap-1">
                <Button variant="ghost" size="icon-sm" onClick={onEdit} aria-label={`Editar ${supplier.name}`}>
                    <Pencil />
                </Button>
                <DeleteButton
                    url={route('configuracoes.fornecedores.destroy', supplier.id)}
                    label={`Excluir ${supplier.name}`}
                    title={`Excluir o fornecedor ${supplier.name}?`}
                    description="Os lançamentos continuam no financeiro, só ficam sem fornecedor."
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 />
                </DeleteButton>
            </div>
        </li>
    );
}

function EditRow({ supplier, onDone }: { supplier: Supplier; onDone: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        name: supplier.name,
        trade_name: supplier.trade_name ?? '',
        document: supplier.document ?? '',
        email: supplier.email ?? '',
        phone: supplier.phone ?? '',
        active: supplier.active,
    });

    return (
        <li className="bg-muted/30 border-b px-6 py-3 last:border-b-0">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    put(route('configuracoes.fornecedores.update', supplier.id), { preserveScroll: true, onSuccess: onDone });
                }}
                className="flex flex-wrap items-center gap-3"
            >
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="min-w-52 flex-1" aria-label="Razão social" />
                <Input value={data.trade_name} onChange={(e) => setData('trade_name', e.target.value)} className="w-44" aria-label="Nome fantasia" />
                <Input value={data.document} onChange={(e) => setData('document', maskCnpj(e.target.value))} className="w-48" aria-label="CNPJ" />
                <Input value={data.email} onChange={(e) => setData('email', e.target.value)} className="w-56" aria-label="E-mail" />
                <Input value={data.phone} onChange={(e) => setData('phone', maskPhone(e.target.value))} className="w-40" aria-label="Telefone" />

                <label className="text-muted-foreground flex items-center gap-2 text-xs">
                    <input
                        type="checkbox"
                        checked={data.active}
                        onChange={(e) => setData('active', e.target.checked)}
                        className="accent-primary size-4"
                    />
                    Ativo
                </label>

                <div className="flex items-center gap-1">
                    <Button type="submit" size="icon-sm" loading={processing} aria-label="Salvar">
                        {!processing && <Check />}
                    </Button>
                    <Button type="button" variant="ghost" size="icon-sm" onClick={onDone} aria-label="Cancelar">
                        <X />
                    </Button>
                </div>

                {errors.name && <p className="text-destructive w-full text-xs font-medium">{errors.name}</p>}
            </form>
        </li>
    );
}
