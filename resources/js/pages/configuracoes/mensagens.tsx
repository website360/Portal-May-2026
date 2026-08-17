import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { Condition, MessageTemplate, Trigger } from '@/types/messages';
import { Head, router, useForm } from '@inertiajs/react';
import { MessageSquareText, Pencil, Plus, Sparkles, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Mensagens', href: '/configuracoes/mensagens' },
];

interface PageProps {
    templates: MessageTemplate[];
    triggers: Record<string, Trigger>;
    operators: Record<string, string>;
    operators_without_value: string[];
    /** O texto que o sistema manda hoje sem nenhum modelo, por gatilho. */
    starters: Record<string, string>;
}

interface FormData {
    trigger: string;
    name: string;
    variations: string[];
    conditions: Condition[];
    priority: number;
    active: boolean;
    [key: string]: unknown;
}

export default function Mensagens({ templates, triggers, operators, operators_without_value, starters }: PageProps) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<MessageTemplate | null>(null);
    const [deleting, setDeleting] = useState<MessageTemplate | null>(null);

    const chaves = Object.keys(triggers);

    function openCreate(trigger?: string) {
        setEditing(null);
        setNovoGatilho(trigger ?? chaves[0]);
        setOpen(true);
    }

    const [novoGatilho, setNovoGatilho] = useState(chaves[0]);

    function openEdit(template: MessageTemplate) {
        setEditing(template);
        setOpen(true);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mensagens" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold">Mensagens do WhatsApp</h2>
                            <p className="text-muted-foreground text-sm">
                                O texto que o sistema manda em cada situação. Sem modelo cadastrado, sai o padrão de fábrica.
                            </p>
                        </div>

                        <Button onClick={() => openCreate()}>
                            <Plus />
                            Novo modelo
                        </Button>
                    </div>

                    {chaves.map((chave) => {
                        const gatilho = triggers[chave];
                        const doGatilho = templates.filter((t) => t.trigger === chave);

                        return (
                            <div key={chave} className="space-y-2">
                                <div className="space-y-0.5 px-1">
                                    <h3 className="text-sm font-medium">{gatilho.label}</h3>
                                    <p className="text-muted-foreground text-xs">{gatilho.description}</p>
                                </div>

                                {doGatilho.length === 0 ? (
                                    <Card>
                                        <div className="flex flex-col items-center gap-3 px-6 py-10 text-center">
                                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                                <MessageSquareText className="size-5" />
                                            </span>
                                            <div className="space-y-1">
                                                <p className="text-sm font-medium">Ainda usando o texto padrão</p>
                                                <p className="text-muted-foreground text-sm">
                                                    Crie um modelo para escrever do seu jeito — e mais de uma variação para o cliente não receber
                                                    sempre a mesma mensagem.
                                                </p>
                                            </div>
                                            <Button variant="outline" className="mt-1" onClick={() => openCreate(chave)}>
                                                <Plus />
                                                Novo modelo
                                            </Button>
                                        </div>
                                    </Card>
                                ) : (
                                    <Card className="divide-y">
                                        {doGatilho.map((template, indice) => (
                                            <div key={template.id} className="flex flex-wrap items-start justify-between gap-3 p-4">
                                                <div className="min-w-0 space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">{template.name}</span>
                                                        {!template.active && <Badge variant="muted">Desativado</Badge>}
                                                        <Badge variant="muted">
                                                            {template.variations.length}{' '}
                                                            {template.variations.length === 1 ? 'variação' : 'variações'}
                                                        </Badge>
                                                    </div>

                                                    <p className="text-muted-foreground text-sm">
                                                        {template.rules.length === 0
                                                            ? 'Sem regra — serve para qualquer caso.'
                                                            : `Quando ${template.rules.join(' e ')}.`}
                                                    </p>

                                                    {/*
                                                        A ordem importa e não é óbvia: quem lê precisa saber que o
                                                        primeiro que bate é o que sai, e nenhum outro.
                                                    */}
                                                    {doGatilho.length > 1 && (
                                                        <p className="text-muted-foreground text-xs">
                                                            {indice === 0 ? 'Testado primeiro' : `${indice + 1}º na fila`} · prioridade{' '}
                                                            {template.priority}
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
                        );
                    })}
                </div>

                <TemplateSheet
                    open={open}
                    onOpenChange={setOpen}
                    template={editing}
                    triggerPadrao={novoGatilho}
                    triggers={triggers}
                    operators={operators}
                    semValor={operators_without_value}
                    starters={starters}
                />

                <ConfirmDialog
                    open={deleting !== null}
                    onOpenChange={() => setDeleting(null)}
                    title={`Excluir o modelo ${deleting?.name}?`}
                    description="Se não sobrar nenhum modelo que sirva, o sistema volta a mandar o texto padrão."
                    onConfirm={() => {
                        if (!deleting) return;
                        router.delete(route('configuracoes.mensagens.destroy', deleting.id), {
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
    triggerPadrao,
    triggers,
    operators,
    semValor,
    starters,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    template: MessageTemplate | null;
    triggerPadrao: string;
    triggers: Record<string, Trigger>;
    operators: Record<string, string>;
    semValor: string[];
    starters: Record<string, string>;
}) {
    const { data, setData, post, put, processing, errors, clearErrors } = useForm<FormData>({
        trigger: triggerPadrao,
        name: '',
        variations: [''],
        conditions: [],
        priority: 0,
        active: true,
    });

    const [ativa, setAtiva] = useState(0);
    const [previa, setPrevia] = useState('');
    const areaRef = useRef<HTMLTextAreaElement>(null);
    const editando = template !== null;
    const gatilho = triggers[data.trigger];

    useEffect(() => {
        if (!open) return;

        setData(
            template
                ? {
                      trigger: template.trigger,
                      name: template.name,
                      variations: template.variations.length ? template.variations : [''],
                      conditions: template.conditions ?? [],
                      priority: template.priority,
                      active: template.active,
                  }
                : { trigger: triggerPadrao, name: '', variations: [''], conditions: [], priority: 0, active: true },
        );
        setAtiva(0);
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, template?.id, triggerPadrao]);

    /*
     * A prévia é renderizada pelo servidor, com a mesma conta do envio de
     * verdade — inclusive os blocos opcionais. Uma prévia que renderiza
     * diferente do envio é pior do que não ter prévia.
     */
    const texto = data.variations[ativa] ?? '';

    useEffect(() => {
        if (!open) return;

        const timer = setTimeout(async () => {
            const params = new URLSearchParams({ trigger: data.trigger, body: texto });
            const resposta = await fetch(`${route('configuracoes.mensagens.previa')}?${params}`, {
                headers: { Accept: 'application/json' },
            });

            if (resposta.ok) setPrevia((await resposta.json()).text);
        }, 300);

        return () => clearTimeout(timer);
    }, [open, texto, data.trigger]);

    /** Insere o marcador onde o cursor estava — o texto raramente cresce só no fim. */
    function inserir(marcador: string) {
        const area = areaRef.current;
        const trecho = `{{${marcador}}}`;
        const corte = area ? area.selectionStart : texto.length;

        mudarVariacao(ativa, texto.slice(0, corte) + trecho + texto.slice(area ? area.selectionEnd : texto.length));

        requestAnimationFrame(() => {
            area?.focus();
            area?.setSelectionRange(corte + trecho.length, corte + trecho.length);
        });
    }

    function mudarVariacao(indice: number, valor: string) {
        setData(
            'variations',
            data.variations.map((v, i) => (i === indice ? valor : v)),
        );
    }

    function submeter(e: React.FormEvent) {
        e.preventDefault();

        const opcoes = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        editando ? put(route('configuracoes.mensagens.update', template.id), opcoes) : post(route('configuracoes.mensagens.store'), opcoes);
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-3xl">
                <SheetHeader className="border-b px-6 py-4">
                    <SheetTitle>{editando ? 'Editar modelo' : 'Novo modelo de mensagem'}</SheetTitle>
                    <SheetDescription>
                        As regras decidem quando ele serve; as variações, de quantos jeitos ele pode sair.
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={submeter} className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                        <div className="grid content-start gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,14rem)]">
                            <div className="grid content-start gap-1.5">
                                <Label htmlFor="nome">Nome</Label>
                                <Input
                                    id="nome"
                                    autoFocus
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Manutenção — texto do mês"
                                />
                                <p className="text-muted-foreground text-xs">Só para você achar na lista.</p>
                                {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                            </div>

                            <div className="grid content-start gap-1.5">
                                <Label>Gatilho</Label>
                                <Select value={data.trigger} onValueChange={(v) => setData('trigger', v)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(triggers).map(([chave, t]) => (
                                            <SelectItem key={chave} value={chave}>
                                                {t.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-muted-foreground text-xs">{gatilho?.description}</p>
                            </div>
                        </div>

                        {/* ── Regras ─────────────────────────────────────────── */}
                        <section className="space-y-2">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-sm font-medium">Quando usar</h3>
                                    <p className="text-muted-foreground text-xs">
                                        {data.conditions.length === 0
                                            ? 'Sem regra, serve para qualquer manutenção.'
                                            : 'Todas precisam ser verdadeiras.'}
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setData('conditions', [
                                            ...data.conditions,
                                            { field: gatilho.fields[0].key, operator: 'igual', value: '' },
                                        ])
                                    }
                                >
                                    <Plus />
                                    Regra
                                </Button>
                            </div>

                            {data.conditions.map((condicao, indice) => (
                                <div key={indice} className="flex flex-wrap items-center gap-2">
                                    <Select
                                        value={condicao.field}
                                        onValueChange={(v) =>
                                            setData(
                                                'conditions',
                                                data.conditions.map((c, i) => (i === indice ? { ...c, field: v } : c)),
                                            )
                                        }
                                    >
                                        <SelectTrigger className="min-w-0 flex-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {gatilho.fields.map((campo) => (
                                                <SelectItem key={campo.key} value={campo.key}>
                                                    {campo.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <Select
                                        value={condicao.operator}
                                        onValueChange={(v) =>
                                            setData(
                                                'conditions',
                                                data.conditions.map((c, i) => (i === indice ? { ...c, operator: v } : c)),
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-40 shrink-0">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(operators).map(([chave, rotulo]) => (
                                                <SelectItem key={chave} value={chave}>
                                                    {rotulo}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    {!semValor.includes(condicao.operator) && (
                                        <Input
                                            className="w-36 shrink-0"
                                            value={condicao.value ?? ''}
                                            placeholder={
                                                gatilho.fields.find((f) => f.key === condicao.field)?.type === 'boolean' ? 'sim ou não' : 'valor'
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'conditions',
                                                    data.conditions.map((c, i) => (i === indice ? { ...c, value: e.target.value } : c)),
                                                )
                                            }
                                        />
                                    )}

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Remover regra"
                                        onClick={() =>
                                            setData(
                                                'conditions',
                                                data.conditions.filter((_, i) => i !== indice),
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                </div>
                            ))}
                        </section>

                        {/* ── Variações ──────────────────────────────────────── */}
                        <section className="space-y-2">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-sm font-medium">O texto</h3>
                                    <p className="text-muted-foreground text-xs">
                                        Com mais de uma variação, o sistema sorteia uma a cada envio.
                                    </p>
                                </div>

                                <div className="flex gap-2">
                                    {starters[data.trigger] && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => mudarVariacao(ativa, starters[data.trigger])}
                                        >
                                            <Sparkles />
                                            Texto padrão
                                        </Button>
                                    )}

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setData('variations', [...data.variations, '']);
                                            setAtiva(data.variations.length);
                                        }}
                                    >
                                        <Plus />
                                        Variação
                                    </Button>
                                </div>
                            </div>

                            {data.variations.length > 1 && (
                                <div className="flex flex-wrap gap-1">
                                    {data.variations.map((_, indice) => (
                                        <button
                                            key={indice}
                                            type="button"
                                            onClick={() => setAtiva(indice)}
                                            className={cn(
                                                'flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                                                indice === ativa ? 'bg-accent text-accent-foreground' : 'text-muted-foreground hover:bg-muted',
                                            )}
                                        >
                                            Variação {indice + 1}
                                            {indice === ativa && (
                                                <X
                                                    className="size-3"
                                                    role="button"
                                                    aria-label={`Remover variação ${indice + 1}`}
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        setData(
                                                            'variations',
                                                            data.variations.filter((_, i) => i !== indice),
                                                        );
                                                        setAtiva(Math.max(0, indice - 1));
                                                    }}
                                                />
                                            )}
                                        </button>
                                    ))}
                                </div>
                            )}

                            <Textarea
                                ref={areaRef}
                                rows={12}
                                value={texto}
                                onChange={(e) => mudarVariacao(ativa, e.target.value)}
                                placeholder="Olá, {{cliente.contato}}! Concluímos a manutenção do site {{site.url}}."
                                className="font-mono text-sm"
                            />
                            {errors[`variations.${ativa}`] && <p className="text-destructive text-xs">{errors[`variations.${ativa}`] as string}</p>}
                            {errors.variations && <p className="text-destructive text-xs">{errors.variations}</p>}

                            <div className="flex flex-wrap gap-1">
                                {gatilho?.variables.map((variavel) => (
                                    <button
                                        key={variavel.key}
                                        type="button"
                                        onClick={() => inserir(variavel.key)}
                                        title={variavel.label}
                                        className="bg-muted hover:bg-accent text-muted-foreground hover:text-accent-foreground rounded px-2 py-1 font-mono text-xs transition-colors"
                                    >
                                        {variavel.key}
                                    </button>
                                ))}
                            </div>

                            <p className="text-muted-foreground text-xs">
                                *negrito*, _itálico_ como no WhatsApp. Um trecho entre <code>[[colchetes duplos]]</code> só aparece se os marcadores
                                dentro dele tiverem valor.
                            </p>
                        </section>

                        {/* ── Prévia ─────────────────────────────────────────── */}
                        <section className="space-y-2">
                            <h3 className="text-sm font-medium">Como o cliente recebe</h3>

                            <div className="bg-muted/50 rounded-lg p-3">
                                <div className="bg-background max-w-md rounded-lg rounded-tl-none border p-3 shadow-xs">
                                    <p className="text-sm whitespace-pre-wrap">{previa || 'Escreva o texto acima.'}</p>
                                </div>
                            </div>

                            <p className="text-muted-foreground text-xs">Com dados de exemplo — o envio de verdade usa os do cliente.</p>
                        </section>

                        {/* ── Ordem ──────────────────────────────────────────── */}
                        <section className="grid content-start gap-5 sm:grid-cols-[minmax(0,10rem)_minmax(0,1fr)]">
                            <div className="grid content-start gap-1.5">
                                <Label htmlFor="prioridade">Prioridade</Label>
                                <Input
                                    id="prioridade"
                                    inputMode="numeric"
                                    value={String(data.priority)}
                                    onChange={(e) => setData('priority', Number(e.target.value.replace(/\D/g, '')) || 0)}
                                />
                            </div>

                            <div className="flex items-end pb-1">
                                <p className="text-muted-foreground text-xs">
                                    Quando mais de um modelo serve, sai o de maior prioridade. Deixe a regra específica acima da geral.
                                </p>
                            </div>
                        </section>

                        <label className="flex cursor-pointer items-center gap-2">
                            <Checkbox checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                            <span className="text-sm">Ativo</span>
                        </label>
                    </div>

                    <div className="flex justify-end gap-2 border-t px-6 py-4">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {editando ? 'Salvar' : 'Criar modelo'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
