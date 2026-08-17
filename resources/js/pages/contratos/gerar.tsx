import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { currencyToNumber } from '@/lib/masks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { ClientOption, GeneratorTemplate } from '@/types/contracts';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, ExternalLink, Eye, FileText, RefreshCw, ScrollText } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contratos', href: '/contratos' },
    { title: 'Gerar', href: '/contratos/gerar' },
];

interface GerarPageProps {
    templates: GeneratorTemplate[];
    clients: ClientOption[];
    nextNumber: string;
}

interface FormData {
    client_id: string;
    contract_template_id: string;
    title: string;
    service: string;
    value: string;
    starts_at: string;
    ends_at: string;
    signed_at: string;
    notes: string;
    variables: Record<string, string>;

    [key: string]: string | Record<string, string>;
}

const today = () => new Date().toISOString().slice(0, 10);

export default function Gerar({ templates, clients, nextNumber }: GerarPageProps) {
    const [preview, setPreview] = useState(false);
    const [previewUrl, setPreviewUrl] = useState('');
    // A prévia mostra dados antigos: há mudanças no formulário que ela ainda não reflete.
    const [stale, setStale] = useState(false);

    const { data, setData, post, processing, errors, clearErrors } = useForm<FormData>({
        client_id: '',
        contract_template_id: '',
        title: '',
        service: '',
        value: '',
        starts_at: today(),
        ends_at: '',
        signed_at: '',
        notes: '',
        variables: {},
    });

    const template = templates.find((t) => String(t.id) === data.contract_template_id) ?? null;

    /**
     * Escolher o serviço é escolher o contrato. O título e o serviço vêm do
     * modelo, e os campos que ele pede aparecem abaixo.
     */
    function chooseTemplate(id: string) {
        const chosen = templates.find((t) => String(t.id) === id);

        clearErrors();
        setData((current) => ({
            ...current,
            contract_template_id: id,
            service: chosen?.name ?? current.service,
            title: current.title || (chosen ? `Contrato de ${chosen.name}` : ''),
            // Os campos são de cada modelo: trocar de modelo zera o que foi digitado.
            variables: {},
        }));
    }

    function change<K extends keyof FormData>(field: K, value: FormData[K]) {
        clearErrors(field as string);
        setData(field as string, value);
    }

    /**
     * A prévia é o PDF de verdade, montado pelo servidor com o que está no
     * formulário e aberto no visualizador do navegador.
     *
     * Um texto na tela não mostraria paginação, timbrado nem tipografia — que é
     * justamente o que se quer conferir antes de gerar. E desenhar tudo isso em
     * HTML seria uma segunda versão do documento, fadada a divergir da primeira.
     */
    function previewUrlFor(): string {
        const params = new URLSearchParams({ contract_template_id: data.contract_template_id, formato: 'pdf' });

        if (data.client_id) params.set('client_id', data.client_id);
        if (data.title) params.set('title', data.title);
        if (data.service) params.set('service', data.service);
        if (data.starts_at) params.set('starts_at', data.starts_at);
        if (data.ends_at) params.set('ends_at', data.ends_at);

        const valor = currencyToNumber(data.value);
        if (valor) params.set('value', valor);

        for (const [key, value] of Object.entries(data.variables)) {
            if (value) params.set(`variables[${key}]`, value);
        }

        // #toolbar=0: o visualizador embutido não precisa da barra própria.
        return `${route('contratos.previa')}?${params}#toolbar=0&navpanes=0`;
    }

    function refreshPreview() {
        setPreviewUrl(previewUrlFor());
        setStale(false);
    }

    /*
     * Trocar o serviço ou o cliente troca o documento inteiro: aí recarregar é
     * o que se espera.
     */
    useEffect(() => {
        if (!data.contract_template_id) {
            setPreviewUrl('');
            setStale(false);
            return;
        }

        const timer = setTimeout(refreshPreview, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.contract_template_id, data.client_id]);

    /*
     * Os demais campos mudam uma linha no meio de sete páginas. Recarregar a
     * cada tecla jogava a leitura de volta para a primeira página e fazia
     * esperar por nada — aqui só marca a prévia como desatualizada, e quem lê
     * decide quando atualizar.
     */
    useEffect(() => {
        if (!previewUrl) return;

        setStale(previewUrlFor() !== previewUrl);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.title, data.service, data.value, data.starts_at, data.ends_at, data.variables]);

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(route('contratos.store'), { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gerar contrato" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Gerar contrato</h1>
                        <p className="text-muted-foreground text-sm">
                            O serviço escolhido decide o texto. Será o contrato <span className="tabular font-medium">{nextNumber}</span>.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={route('contratos.index')}>
                            <ArrowLeft />
                            Contratos
                        </Link>
                    </Button>
                </div>

                {templates.length === 0 ? (
                    <Card>
                        <div className="flex flex-col items-center gap-3 px-6 py-16 text-center">
                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                <ScrollText className="size-5" />
                            </span>
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Nenhum modelo de contrato</p>
                                <p className="text-muted-foreground text-sm">O gerador precisa de pelo menos um modelo. Cada serviço tem o seu.</p>
                            </div>
                            <Button variant="outline" className="mt-1" asChild>
                                <Link href="/configuracoes/modelos-de-contrato">Criar um modelo</Link>
                            </Button>
                        </div>
                    </Card>
                ) : (
                    <form onSubmit={submit} className="grid min-w-0 gap-6 lg:grid-cols-[1fr_1fr]">
                        <div className="flex min-w-0 flex-col gap-6">
                            <Card>
                                <CardContent className="space-y-4 p-5">
                                    <p className="text-sm font-medium">1. Qual serviço foi contratado</p>

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {templates.map((option) => (
                                            <button
                                                key={option.id}
                                                type="button"
                                                onClick={() => chooseTemplate(String(option.id))}
                                                className={cn(
                                                    'flex flex-col items-start gap-0.5 rounded-lg border px-3 py-2.5 text-left transition-all duration-150',
                                                    'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden active:scale-[0.98]',
                                                    data.contract_template_id === String(option.id)
                                                        ? 'border-primary bg-accent text-accent-foreground shadow-xs'
                                                        : 'border-input bg-background hover:border-primary/30',
                                                )}
                                            >
                                                <span className="text-sm font-medium">{option.name}</span>
                                                {option.description && <span className="text-muted-foreground text-xs">{option.description}</span>}
                                            </button>
                                        ))}
                                    </div>

                                    {errors.contract_template_id && (
                                        <p className="text-destructive text-xs font-medium">{errors.contract_template_id}</p>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="space-y-4 p-5">
                                    <p className="text-sm font-medium">2. Para quem</p>

                                    <Field label="Cliente" required error={errors.client_id}>
                                        <Combobox
                                            id="client_id"
                                            value={data.client_id}
                                            onChange={(value) => change('client_id', value)}
                                            options={clients.map((c) => ({ value: String(c.id), label: c.name, search: c.search }))}
                                            placeholder="Escolha o cliente"
                                            searchPlaceholder="Buscar cliente…"
                                            emptyText="Nenhum cliente com esse nome."
                                        />
                                    </Field>

                                    <Field label="Título do contrato" required error={errors.title}>
                                        <Input
                                            id="title"
                                            value={data.title}
                                            onChange={(e) => change('title', e.target.value)}
                                            placeholder="Contrato de prestação de serviços"
                                        />
                                    </Field>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Valor" error={errors.value}>
                                            <CurrencyInput id="value" value={data.value} onChange={(v) => change('value', v)} placeholder="0,00" />
                                        </Field>

                                        <Field label="Serviço" required error={errors.service}>
                                            <Input id="service" value={data.service} onChange={(e) => change('service', e.target.value)} />
                                        </Field>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Início da vigência" required error={errors.starts_at}>
                                            <Input
                                                id="starts_at"
                                                type="date"
                                                value={data.starts_at}
                                                onChange={(e) => change('starts_at', e.target.value)}
                                            />
                                        </Field>

                                        <Field label="Fim da vigência" error={errors.ends_at} hint="Em branco: prazo indeterminado.">
                                            <Input
                                                id="ends_at"
                                                type="date"
                                                value={data.ends_at}
                                                onChange={(e) => change('ends_at', e.target.value)}
                                            />
                                        </Field>
                                    </div>
                                </CardContent>
                            </Card>

                            {template && template.fields.length > 0 && (
                                <Card>
                                    <CardContent className="space-y-4 p-5">
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium">3. O que este contrato pergunta</p>
                                            <p className="text-muted-foreground text-xs">
                                                Campos que só este modelo pede. Deixar em branco mantém o marcador visível no texto.
                                            </p>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            {template.fields.map((field) => (
                                                <Field key={field.key} label={field.label}>
                                                    <Input
                                                        value={data.variables[field.key] ?? ''}
                                                        onChange={(e) => setData('variables', { ...data.variables, [field.key]: e.target.value })}
                                                        placeholder={`{{${field.key}}}`}
                                                    />
                                                </Field>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            <Card>
                                <CardContent className="space-y-4 p-5">
                                    <p className="text-sm font-medium">Assinatura e observações</p>

                                    <Field
                                        label="Já foi assinado em"
                                        error={errors.signed_at}
                                        hint="Sem data, o contrato fica como “aguardando assinatura”."
                                    >
                                        <Input
                                            id="signed_at"
                                            type="date"
                                            className="w-44"
                                            value={data.signed_at}
                                            onChange={(e) => change('signed_at', e.target.value)}
                                        />
                                    </Field>

                                    <Field label="Observações internas" error={errors.notes}>
                                        <Textarea
                                            id="notes"
                                            rows={2}
                                            value={data.notes}
                                            onChange={(e) => change('notes', e.target.value)}
                                            placeholder="Combinados que não entram no contrato."
                                        />
                                    </Field>
                                </CardContent>
                            </Card>

                            <div className="flex items-center gap-2">
                                <Button type="submit" loading={processing} disabled={!template}>
                                    {!processing && <Check />}
                                    Gerar contrato
                                </Button>

                                <Button type="button" variant="outline" onClick={() => router.visit(route('contratos.index'))}>
                                    Cancelar
                                </Button>
                            </div>
                        </div>

                        {/* Prévia ao lado: o contrato é longo, e conferir depois de salvo é tarde. */}
                        <div className="min-w-0">
                            <Card className="lg:sticky lg:top-6">
                                <div className="flex items-center justify-between gap-3 border-b px-5 py-3">
                                    <span className="flex items-center gap-2 text-sm font-medium">
                                        <Eye className="text-muted-foreground size-4" />
                                        Como vai ficar
                                    </span>

                                    {previewUrl && (
                                        <div className="flex items-center gap-1">
                                            {/* Só recarrega quando você manda: assim a leitura não volta para a página 1. */}
                                            <Button
                                                type="button"
                                                variant={stale ? 'default' : 'ghost'}
                                                size="sm"
                                                onClick={refreshPreview}
                                                disabled={!stale}
                                            >
                                                <RefreshCw />
                                                {stale ? 'Atualizar' : 'Atualizada'}
                                            </Button>

                                            <Button type="button" variant="ghost" size="sm" asChild>
                                                <a href={previewUrl} target="_blank" rel="noreferrer">
                                                    <ExternalLink />
                                                    Abrir
                                                </a>
                                            </Button>

                                            <Button type="button" variant="ghost" size="sm" onClick={() => setPreview((v) => !v)}>
                                                {preview ? 'Diminuir' : 'Ampliar'}
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                <CardContent className="p-0">
                                    {previewUrl ? (
                                        /*
                                            O PDF de verdade no visualizador do navegador: paginação,
                                            timbrado e tipografia são exatamente os do arquivo final.
                                            A chave force o recarregamento quando os dados mudam.
                                        */
                                        <iframe
                                            key={previewUrl}
                                            src={previewUrl}
                                            title="Prévia do contrato"
                                            className={cn('w-full border-0', preview ? 'h-[80vh]' : 'h-[560px]')}
                                        />
                                    ) : (
                                        <div className="text-muted-foreground flex flex-col items-center gap-2 px-5 py-16 text-center text-sm">
                                            <FileText className="size-5" />
                                            {template ? 'Montando o documento…' : 'Escolha o serviço para ver o contrato.'}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    required,
    error,
    hint,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>
                {label}
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            {children}
            {hint && !error && <p className="text-muted-foreground text-xs">{hint}</p>}
            {error && <p className="text-destructive text-xs font-medium">{error}</p>}
        </div>
    );
}
