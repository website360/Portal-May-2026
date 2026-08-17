import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { ContractArt, ContractTemplate, PlaceholderCatalog } from '@/types/contracts';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, CircleAlert, CircleCheck, Copy, Pencil, Plus, ScrollText, Trash2, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Modelos de contrato', href: '/configuracoes/modelos-de-contrato' },
];

interface PageProps {
    templates: ContractTemplate[];
    catalog: PlaceholderCatalog;
    art: ContractArt;
}

const FACE_LABELS: Record<string, string> = {
    regular: 'normal',
    bold: 'negrito',
    italic: 'itálico',
    bolditalic: 'itálico negrito',
};

/**
 * O que o PDF encontrou de arte e tipografia.
 *
 * Sem isto, largar um arquivo com o nome errado falhava em silêncio — o
 * contrato saía sem timbrado e nada na tela dizia por quê.
 */
function ArtStatus({ art }: { art: ContractArt }) {
    const extensions = Object.keys(art.extensions).join(', ');

    return (
        <Card className="divide-y">
            {/*
                Pasta em public/ com nome de módulo derruba a rota inteira e o
                sintoma — 404 numa página que existe — não aponta para a causa.
            */}
            {art.shadowing.length > 0 && (
                <div className="bg-destructive/5 flex items-start gap-2 p-4">
                    <TriangleAlert className="text-destructive mt-0.5 size-4 shrink-0" />
                    <div className="space-y-1">
                        <p className="text-destructive text-sm font-medium">
                            {art.shadowing.map((f) => `public/${f}`).join(', ')} está derrubando a página de mesmo nome
                        </p>
                        <p className="text-muted-foreground text-xs">
                            A pasta <code>public/</code> é a raiz do site: uma pasta com nome de módulo é encontrada antes da rota, e a página passa a
                            responder 404. Mova os arquivos para <code>{art.letterhead.path.replace(/\/[^/]+$/, '')}</code> e apague a pasta.
                        </p>
                    </div>
                </div>
            )}

            <div className="flex flex-wrap items-start justify-between gap-3 p-4">
                <div className="min-w-0 space-y-1">
                    <div className="flex items-center gap-2">
                        {art.letterhead.found ? (
                            <CircleCheck className="text-success size-4 shrink-0" />
                        ) : (
                            <CircleAlert className="text-muted-foreground size-4 shrink-0" />
                        )}
                        <span className="text-sm font-medium">Papel timbrado</span>
                    </div>

                    <p className="text-muted-foreground text-xs">
                        {art.letterhead.found ? (
                            <>
                                Encontrado em <code>{art.letterhead.path}</code>. Ele entra no fundo de todas as páginas.
                            </>
                        ) : (
                            <>
                                Nenhum arquivo em <code>{art.letterhead.path}</code>. Aceita {extensions}. Sem ele, o PDF usa um cabeçalho simples.
                            </>
                        )}
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap items-start justify-between gap-3 p-4">
                <div className="min-w-0 space-y-1">
                    <div className="flex items-center gap-2">
                        {art.font.found ? (
                            <CircleCheck className="text-success size-4 shrink-0" />
                        ) : (
                            <CircleAlert className="text-muted-foreground size-4 shrink-0" />
                        )}
                        <span className="text-sm font-medium">Fonte: {art.font.name}</span>
                    </div>

                    <p className="text-muted-foreground text-xs">
                        {art.font.found ? (
                            <>
                                Arquivos em <code>{art.font.folder}</code>.
                            </>
                        ) : (
                            <>
                                Coloque <code>regular.ttf</code> em <code>{art.font.folder}</code> para usar a fonte da agência.
                            </>
                        )}
                    </p>

                    {art.font.missing.length > 0 && (
                        <p className="text-warning text-xs">
                            Faltam {art.font.missing.map((face) => FACE_LABELS[face] ?? face).join(', ')} — esses pesos não vão aparecer no PDF.
                        </p>
                    )}
                </div>
            </div>
        </Card>
    );
}

interface FormData {
    name: string;
    description: string;
    body: string;
    active: boolean;
    with_signatures: boolean;

    [key: string]: string | boolean;
}

const EMPTY: FormData = { name: '', description: '', body: '', active: true, with_signatures: false };

export default function ModelosDeContrato({ templates, catalog, art }: PageProps) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<ContractTemplate | null>(null);
    const [deleting, setDeleting] = useState<ContractTemplate | null>(null);

    function openCreate() {
        setEditing(null);
        setOpen(true);
    }

    function openEdit(template: ContractTemplate) {
        setEditing(template);
        setOpen(true);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Modelos de contrato" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold">Modelos de contrato</h2>
                            <p className="text-muted-foreground text-sm">Um por serviço. É o modelo que decide o texto que sai no gerador.</p>
                        </div>

                        <Button onClick={openCreate}>
                            <Plus />
                            Novo modelo
                        </Button>
                    </div>

                    <ArtStatus art={art} />

                    {templates.length === 0 ? (
                        <Card>
                            <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
                                <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                    <ScrollText className="size-5" />
                                </span>
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">Nenhum modelo ainda</p>
                                    <p className="text-muted-foreground text-sm">
                                        Escreva o contrato uma vez, com marcadores no lugar dos dados que mudam.
                                    </p>
                                </div>
                                <Button variant="outline" className="mt-1" onClick={openCreate}>
                                    <Plus />
                                    Novo modelo
                                </Button>
                            </div>
                        </Card>
                    ) : (
                        <Card className="divide-y">
                            {templates.map((template) => (
                                <div key={template.id} className="flex flex-wrap items-start justify-between gap-3 p-4">
                                    <div className="min-w-0 space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">{template.name}</span>
                                            {!template.active && <Badge variant="muted">Desativado</Badge>}
                                            {template.contracts_count > 0 && (
                                                <span className="text-muted-foreground text-xs">
                                                    {template.contracts_count} {template.contracts_count === 1 ? 'contrato' : 'contratos'}
                                                </span>
                                            )}
                                        </div>

                                        {template.description && <p className="text-muted-foreground text-sm">{template.description}</p>}

                                        {template.fields.length > 0 && (
                                            <p className="text-muted-foreground text-xs">
                                                Pergunta ao gerar: {template.fields.map((f) => f.label).join(', ')}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={`Editar ${template.name}`}
                                            onClick={() => openEdit(template)}
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label={`Excluir ${template.name}`}
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => setDeleting(template)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </Card>
                    )}
                </div>

                <TemplateSheet open={open} onOpenChange={setOpen} template={editing} catalog={catalog} />

                <ConfirmDialog
                    open={deleting !== null}
                    onOpenChange={() => setDeleting(null)}
                    title={`Excluir o modelo ${deleting?.name}?`}
                    description={
                        deleting && deleting.contracts_count > 0
                            ? `Os ${deleting.contracts_count} contratos gerados a partir dele continuam aqui, com o texto que já tinham.`
                            : 'O modelo some do gerador. Nada mais é afetado.'
                    }
                    onConfirm={() => {
                        if (!deleting) return;
                        router.delete(route('configuracoes.modelos.destroy', deleting.id), {
                            preserveScroll: true,
                            onFinish: () => setDeleting(null),
                        });
                    }}
                />
            </SettingsLayout>
        </AppLayout>
    );
}

function TemplateSheet({
    open,
    onOpenChange,
    template,
    catalog,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    template: ContractTemplate | null;
    catalog: PlaceholderCatalog;
}) {
    const { data, setData, post, put, processing, errors, clearErrors } = useForm<FormData>(EMPTY);
    const isEditing = template !== null;

    useEffect(() => {
        if (!open) return;

        setData(
            template
                ? {
                      name: template.name,
                      description: template.description ?? '',
                      body: template.body,
                      active: template.active,
                      with_signatures: template.with_signatures,
                  }
                : EMPTY,
        );
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, template?.id]);

    /** Insere o marcador no fim do texto — mais simples que caçar o cursor. */
    function insert(placeholder: string) {
        setData('body', `${data.body}{{${placeholder}}}`);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        if (isEditing) {
            put(route('configuracoes.modelos.update', template.id), options);
        } else {
            post(route('configuracoes.modelos.store'), options);
        }
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            {/* Largo: escrever contrato numa coluna estreita é sofrido. */}
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-3xl">
                <SheetHeader className="border-b p-6 text-left">
                    <div className="space-y-1.5 pr-8">
                        <SheetTitle>{isEditing ? `Editar ${template.name}` : 'Novo modelo de contrato'}</SheetTitle>
                        <SheetDescription>Escreva o contrato uma vez. Onde os dados mudam, use um marcador.</SheetDescription>
                    </div>
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div className="grid min-h-0 flex-1 content-start gap-5 overflow-y-auto p-6">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">
                                    Serviço<span className="text-destructive ml-0.5">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Hospedagem + Manutenção"
                                />
                                {errors.name && <p className="text-destructive text-xs font-medium">{errors.name}</p>}
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="description">Descrição</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Aparece na escolha do serviço"
                                />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <Label htmlFor="body">
                                    Texto do contrato<span className="text-destructive ml-0.5">*</span>
                                </Label>
                                <span className="text-muted-foreground text-xs">
                                    <code># título</code> · <code>**negrito**</code> · <code>- lista</code> · <code>---</code> linha
                                </span>
                            </div>

                            <Textarea
                                id="body"
                                rows={18}
                                value={data.body}
                                onChange={(e) => setData('body', e.target.value)}
                                className="font-mono text-xs leading-relaxed"
                                placeholder={
                                    '# Cláusula primeira — do objeto\n\nA CONTRATADA prestará a {{cliente.razao_social}}, inscrita no CNPJ {{cliente.documento}}, os serviços de…'
                                }
                            />
                            {errors.body && <p className="text-destructive text-xs font-medium">{errors.body}</p>}
                        </div>

                        <div className="space-y-3 rounded-lg border p-4">
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Marcadores</p>
                                <p className="text-muted-foreground text-xs">
                                    Clique para inserir. Um marcador fora desta lista — <code>{'{{prazo_aviso}}'}</code>, por exemplo — vira pergunta
                                    na hora de gerar.
                                </p>
                            </div>

                            {Object.entries(catalog).map(([group, items]) => (
                                <div key={group} className="space-y-1.5">
                                    <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{group}</p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {Object.entries(items).map(([key, description]) => (
                                            <button
                                                key={key}
                                                type="button"
                                                title={description}
                                                onClick={() => insert(key)}
                                                className={cn(
                                                    'bg-muted hover:bg-accent inline-flex cursor-pointer items-center gap-1 rounded-md px-2 py-1 font-mono text-[11px]',
                                                    'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden',
                                                )}
                                            >
                                                <Copy className="size-3 opacity-50" />
                                                {key}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <label className="flex cursor-pointer items-start gap-2.5">
                            <Checkbox checked={data.active} onCheckedChange={(checked) => setData('active', checked === true)} className="mt-0.5" />
                            <span className="grid gap-0.5">
                                <span className="text-sm font-medium">Disponível no gerador</span>
                                <span className="text-muted-foreground text-xs">
                                    Desmarcado, some da escolha de serviço. Os contratos já gerados continuam.
                                </span>
                            </span>
                        </label>

                        <label className="flex cursor-pointer items-start gap-2.5">
                            <Checkbox
                                checked={data.with_signatures}
                                onCheckedChange={(checked) => setData('with_signatures', checked === true)}
                                className="mt-0.5"
                            />
                            <span className="grid gap-0.5">
                                <span className="text-sm font-medium">Incluir linhas de assinatura no PDF</span>
                                <span className="text-muted-foreground text-xs">
                                    Marque para contrato impresso. Indo para a Clicksign, deixe desmarcado — quem assina é a plataforma, e a linha em
                                    branco no papel só confunde.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div className="bg-muted/30 flex items-center justify-end gap-2 border-t p-6">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>

                        <Button type="submit" loading={processing}>
                            {!processing && <Check />}
                            {isEditing ? 'Salvar alterações' : 'Criar modelo'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
