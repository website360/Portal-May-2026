import { ColorPicker } from '@/components/settings/color-picker';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DeleteButton } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { colorOf } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Pencil, Plus, X } from 'lucide-react';
import { useState } from 'react';

interface Tag {
    id: number;
    name: string;
    color: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Etiquetas', href: '/configuracoes/financeiro/etiquetas' },
];

export default function Etiquetas({ tags, colors }: { tags: Tag[]; colors: string[] }) {
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Etiquetas" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">Etiquetas</h2>
                        <p className="text-muted-foreground text-sm">
                            Rótulos livres para marcar lançamentos. Um lançamento pode ter várias — use para cruzar filtros e tirar relatórios.
                        </p>
                    </div>

                    <CreateForm colors={colors} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Suas etiquetas</CardTitle>
                            <CardDescription>{tags.length === 0 ? 'Nenhuma ainda' : `${tags.length} etiqueta${tags.length === 1 ? '' : 's'}`}</CardDescription>
                        </CardHeader>

                        {tags.length > 0 && (
                            <ul className="border-t">
                                {tags.map((tag) =>
                                    editingId === tag.id ? (
                                        <EditRow key={tag.id} tag={tag} colors={colors} onDone={() => setEditingId(null)} />
                                    ) : (
                                        <ReadRow key={tag.id} tag={tag} onEdit={() => setEditingId(tag.id)} />
                                    ),
                                )}
                            </ul>
                        )}
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function Dot({ color }: { color: string }) {
    return <span className={cn('size-2.5 shrink-0 rounded-full', colorOf(color).dot)} />;
}

function CreateForm({ colors }: { colors: string[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        color: colors[0] ?? 'blue',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(route('configuracoes.etiquetas.store'), {
            preserveScroll: true,
            onSuccess: () => reset('name'),
        });
    }

    return (
        <Card>
            <form onSubmit={submit} className="flex flex-wrap items-end gap-4 p-4">
                <div className="grid min-w-48 flex-1 gap-1.5">
                    <Label htmlFor="name">Nova etiqueta</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Ex.: Marketing, Recorrente, Projeto X" />
                    {errors.name && <p className="text-destructive text-xs font-medium">{errors.name}</p>}
                </div>

                <div className="grid gap-1.5">
                    <Label>Cor</Label>
                    <ColorPicker value={data.color} onChange={(c) => setData('color', c)} colors={colors} />
                </div>

                <Button type="submit" loading={processing}>
                    <Plus />
                    Adicionar
                </Button>
            </form>
        </Card>
    );
}

function ReadRow({ tag, onEdit }: { tag: Tag; onEdit: () => void }) {
    return (
        <li className="hover:bg-muted/40 flex items-center justify-between gap-3 px-4 py-3 transition-colors">
            <span className="flex items-center gap-2.5">
                <Dot color={tag.color} />
                <span className="font-medium">{tag.name}</span>
            </span>

            <span className="flex items-center gap-1">
                <Button variant="ghost" size="icon-sm" aria-label={`Editar ${tag.name}`} onClick={onEdit}>
                    <Pencil />
                </Button>
                <DeleteButton
                    label={`Excluir ${tag.name}`}
                    description={`A etiqueta "${tag.name}" some dos lançamentos que a tinham. Os lançamentos continuam.`}
                    onConfirm={() => router.delete(route('configuracoes.etiquetas.destroy', tag.id), { preserveScroll: true })}
                />
            </span>
        </li>
    );
}

function EditRow({ tag, colors, onDone }: { tag: Tag; colors: string[]; onDone: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        name: tag.name,
        color: tag.color,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        put(route('configuracoes.etiquetas.update', tag.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    return (
        <li className="bg-muted/30 px-4 py-3">
            <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
                <div className="grid min-w-48 flex-1 gap-1.5">
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus />
                    {errors.name && <p className="text-destructive text-xs font-medium">{errors.name}</p>}
                </div>

                <ColorPicker value={data.color} onChange={(c) => setData('color', c)} colors={colors} />

                <span className="flex items-center gap-1">
                    <Button type="submit" size="icon-sm" loading={processing} aria-label="Salvar">
                        <Check />
                    </Button>
                    <Button type="button" variant="ghost" size="icon-sm" onClick={onDone} aria-label="Cancelar">
                        <X />
                    </Button>
                </span>
            </form>
        </li>
    );
}
