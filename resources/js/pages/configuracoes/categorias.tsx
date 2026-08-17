import { ColorPicker } from '@/components/settings/color-picker';
import { ListSortSelect, type ListSorting } from '@/components/settings/list-sort-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteButton } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { colorOf } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { CategoryType, FinanceCategory } from '@/types/finance';
import { Head, useForm } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Check, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Categorias', href: '/configuracoes/financeiro/categorias' },
];

const TYPE_SEGMENTS = [
    { value: 'expense', label: 'Despesa', icon: ArrowDownLeft, activeClassName: 'text-destructive' },
    { value: 'income', label: 'Receita', icon: ArrowUpRight, activeClassName: 'text-success' },
];

export default function Categorias({ categories, colors, filters }: { categories: FinanceCategory[]; colors: string[]; filters: ListSorting }) {
    const [editingId, setEditingId] = useState<number | null>(null);

    const expenses = categories.filter((category) => category.type === 'expense');
    const income = categories.filter((category) => category.type === 'income');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categorias" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold">Categorias</h2>
                            <p className="text-muted-foreground text-sm">
                                Em que cada lançamento se encaixa. Categoria pertence a uma natureza — o formulário só oferece as do tipo certo.
                            </p>
                        </div>

                        {/* Vale para as duas colunas de uma vez. */}
                        <ListSortSelect
                            url={route('configuracoes.categorias.index')}
                            filters={filters}
                            options={[
                                { value: 'name', label: 'Nome' },
                                { value: 'usage', label: 'Lançamentos' },
                                { value: 'status', label: 'Situação' },
                            ]}
                        />
                    </div>

                    <CreateForm colors={colors} />

                    <div className="grid gap-6 xl:grid-cols-2">
                        <Group
                            title="Despesas"
                            description="Aparecem nas contas a pagar"
                            items={expenses}
                            colors={colors}
                            editingId={editingId}
                            onEdit={setEditingId}
                        />

                        <Group
                            title="Receitas"
                            description="Aparecem nas contas a receber"
                            items={income}
                            colors={colors}
                            editingId={editingId}
                            onEdit={setEditingId}
                        />
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function Group({
    title,
    description,
    items,
    colors,
    editingId,
    onEdit,
}: {
    title: string;
    description: string;
    items: FinanceCategory[];
    colors: string[];
    editingId: number | null;
    onEdit: (id: number | null) => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>
                    {description} · {items.length === 0 ? 'nenhuma ainda' : `${formatNumber(items.length)}`}
                </CardDescription>
            </CardHeader>

            {items.length > 0 && (
                <ul className="border-t">
                    {items.map((category) =>
                        editingId === category.id ? (
                            <EditRow key={category.id} category={category} colors={colors} onDone={() => onEdit(null)} />
                        ) : (
                            <ReadRow key={category.id} category={category} onEdit={() => onEdit(category.id)} />
                        ),
                    )}
                </ul>
            )}
        </Card>
    );
}

function CreateForm({ colors }: { colors: string[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        type: 'expense' as CategoryType,
        color: colors[0] ?? 'blue',
        active: true,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        post(route('configuracoes.categorias.store'), {
            preserveScroll: true,
            onSuccess: () => reset('name'),
        });
    }

    return (
        <Card>
            <form onSubmit={submit} className="grid gap-4 p-4 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nome</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Software e assinaturas" />
                    {errors.name && <p className="text-destructive text-xs font-medium">{errors.name}</p>}
                </div>

                <div className="grid gap-2">
                    <Label>Natureza</Label>
                    <SegmentedControl
                        value={data.type}
                        onChange={(value) => setData('type', value as CategoryType)}
                        options={TYPE_SEGMENTS}
                        aria-label="Natureza"
                        className="w-56"
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

function ReadRow({ category, onEdit }: { category: FinanceCategory; onEdit: () => void }) {
    const tone = colorOf(category.color);
    const used = category.transactions_count ?? 0;

    return (
        <li className="hover:bg-muted/40 flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-6 py-3 transition-colors last:border-b-0">
            <span className={cn('size-3 shrink-0 rounded-full', tone.dot)} />

            <div className="min-w-40 flex-1">
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{category.name}</span>
                    {category.active === false && <Badge variant="muted">Inativa</Badge>}
                </div>
            </div>

            <span className="text-muted-foreground tabular text-xs">
                {used === 0 ? 'sem lançamentos' : `${formatNumber(used)} ${used === 1 ? 'lançamento' : 'lançamentos'}`}
            </span>

            <div className="flex items-center gap-1">
                <Button variant="ghost" size="icon-sm" onClick={onEdit} aria-label={`Editar ${category.name}`}>
                    <Pencil />
                </Button>
                <DeleteButton
                    url={route('configuracoes.categorias.destroy', category.id)}
                    label={`Excluir ${category.name}`}
                    title={`Excluir a categoria ${category.name}?`}
                    description="Os lançamentos continuam no financeiro, só ficam sem categoria."
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 />
                </DeleteButton>
            </div>
        </li>
    );
}

function EditRow({ category, colors, onDone }: { category: FinanceCategory; colors: string[]; onDone: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        name: category.name,
        type: category.type,
        color: category.color,
        active: category.active ?? true,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        put(route('configuracoes.categorias.update', category.id), { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <li className="bg-muted/30 border-b px-6 py-3 last:border-b-0">
            <form onSubmit={submit} className="flex flex-wrap items-center gap-3">
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} className="min-w-40 flex-1" aria-label="Nome" />

                <SegmentedControl
                    value={data.type}
                    onChange={(value) => setData('type', value as CategoryType)}
                    options={TYPE_SEGMENTS}
                    aria-label="Natureza"
                    className="w-52"
                />

                <ColorPicker value={data.color} onChange={(color) => setData('color', color)} colors={colors} />

                <label className="text-muted-foreground flex items-center gap-2 text-xs">
                    <input
                        type="checkbox"
                        checked={data.active}
                        onChange={(e) => setData('active', e.target.checked)}
                        className="accent-primary size-4"
                    />
                    Ativa
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
