import { ColorPicker } from '@/components/settings/color-picker';
import { ListSortSelect, type ListSorting } from '@/components/settings/list-sort-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteButton } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { colorOf } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { PaymentMethod } from '@/types/finance';
import { Head, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Formas de pagamento', href: '/configuracoes/financeiro/formas-de-pagamento' },
];

export default function FormasDePagamento({
    paymentMethods,
    colors,
    filters,
}: {
    paymentMethods: PaymentMethod[];
    colors: string[];
    filters: ListSorting;
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Formas de pagamento" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">Formas de pagamento</h2>
                        <p className="text-muted-foreground text-sm">
                            Por onde o dinheiro entra e sai. Cadastrar, em vez de digitar solto, é o que permite somar depois quanto passou no cartão
                            ou caiu no Pix.
                        </p>
                    </div>

                    <CreateForm colors={colors} />

                    <Card>
                        <CardHeader className="flex flex-wrap items-start justify-between gap-3">
                            <div className="space-y-1.5">
                                <CardTitle>Cadastrados</CardTitle>
                                <CardDescription>
                                    {paymentMethods.length === 0
                                        ? 'Nenhuma forma ainda.'
                                        : `${formatNumber(paymentMethods.length)} ${paymentMethods.length === 1 ? 'forma' : 'formas'}`}
                                </CardDescription>
                            </div>

                            <ListSortSelect
                                url={route('configuracoes.formas.index')}
                                filters={filters}
                                options={[
                                    { value: 'name', label: 'Nome' },
                                    { value: 'usage', label: 'Lançamentos' },
                                    { value: 'status', label: 'Situação' },
                                ]}
                            />
                        </CardHeader>

                        <ul className="border-t">
                            {paymentMethods.map((method) =>
                                editingId === method.id ? (
                                    <EditRow key={method.id} method={method} colors={colors} onDone={() => setEditingId(null)} />
                                ) : (
                                    <ReadRow key={method.id} method={method} onEdit={() => setEditingId(method.id)} />
                                ),
                            )}
                        </ul>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function CreateForm({ colors }: { colors: string[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        color: colors[0] ?? 'blue',
        active: true,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        post(route('configuracoes.formas.store'), {
            preserveScroll: true,
            onSuccess: () => reset('name', 'description'),
        });
    }

    return (
        <Card>
            <form onSubmit={submit} className="grid gap-4 p-4 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nome</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Pix" />
                    {errors.name && <p className="text-destructive text-xs font-medium">{errors.name}</p>}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="description">Descrição</Label>
                    <Input
                        id="description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        placeholder="Chave CNPJ do Banco do Brasil"
                    />
                </div>

                <div className="grid gap-2">
                    <Label>Cor</Label>
                    <div className="flex h-9 items-center">
                        <ColorPicker value={data.color} onChange={(color) => setData('color', color)} colors={colors} />
                    </div>
                </div>

                <Button type="submit" loading={processing} disabled={data.name.trim() === ''}>
                    {!processing && <Plus />}
                    Adicionar
                </Button>
            </form>
        </Card>
    );
}

function ReadRow({ method, onEdit }: { method: PaymentMethod; onEdit: () => void }) {
    const tone = colorOf(method.color);
    const used = method.transactions_count ?? 0;

    return (
        <li className="hover:bg-muted/40 flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-6 py-3 transition-colors last:border-b-0">
            <span className={cn('size-3 shrink-0 rounded-full', tone.dot)} />

            <div className="min-w-40 flex-1">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{method.name}</span>
                    {method.active === false && <Badge variant="muted">Inativo</Badge>}
                </div>
                {method.description && <p className="text-muted-foreground text-xs">{method.description}</p>}
            </div>

            <span className="text-muted-foreground tabular text-xs">
                {used === 0 ? 'sem lançamentos' : `${formatNumber(used)} ${used === 1 ? 'lançamento' : 'lançamentos'}`}
            </span>

            <div className="flex items-center gap-1">
                <Button variant="ghost" size="icon-sm" onClick={onEdit} aria-label={`Editar ${method.name}`}>
                    <Pencil />
                </Button>
                <DeleteButton
                    url={route('configuracoes.formas.destroy', method.id)}
                    label={`Excluir ${method.name}`}
                    title={`Excluir a forma ${method.name}?`}
                    description="Os lançamentos continuam no financeiro, só ficam sem forma de pagamento."
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 />
                </DeleteButton>
            </div>
        </li>
    );
}

function EditRow({ method, colors, onDone }: { method: PaymentMethod; colors: string[]; onDone: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        name: method.name,
        description: method.description ?? '',
        color: method.color,
        active: method.active ?? true,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        put(route('configuracoes.formas.update', method.id), { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <li className="bg-muted/30 border-b px-6 py-3 last:border-b-0">
            <form onSubmit={submit} className="flex flex-wrap items-center gap-3">
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-44" aria-label="Nome" />
                <Input
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="min-w-48 flex-1"
                    aria-label="Descrição"
                />
                <ColorPicker value={data.color} onChange={(color) => setData('color', color)} colors={colors} />

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
