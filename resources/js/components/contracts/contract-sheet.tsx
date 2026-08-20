import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { ClientOption, Contract } from '@/types/contracts';
import { router, useForm } from '@inertiajs/react';
import { Check, Download, FileText, Paperclip, Trash2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface ContractSheetProps {
    /** Controla a abertura — criar e editar usam o mesmo painel. */
    open: boolean;
    /** Nulo = cadastrar um contrato novo; preenchido = editar esse contrato. */
    contract: Contract | null;
    onOpenChange: (open: boolean) => void;
    clients: ClientOption[];
}

interface FormData {
    client_id: string;
    service: string;
    value: string;
    starts_at: string;
    ends_at: string;
    billing_period: string;
    price_review_years: string;
    signed_at: string;
    notes: string;
    pdf: File | null;

    [key: string]: string | File | null;
}

const EMPTY: FormData = {
    client_id: '',
    service: '',
    value: '',
    starts_at: '',
    ends_at: '',
    billing_period: '',
    price_review_years: '2',
    signed_at: '',
    notes: '',
    pdf: null,
};

export function ContractSheet({ open, contract, onOpenChange, clients }: ContractSheetProps) {
    const [showText, setShowText] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors, clearErrors } = useForm<FormData>({ ...EMPTY });

    useEffect(() => {
        if (!open) return;

        clearErrors();
        setShowText(false);

        // Sem contrato é cadastro novo: começa em branco. Com contrato, preenche.
        setData(
            contract
                ? {
                      client_id: String(contract.client_id),
                      service: contract.service,
                      value: contract.value === null ? '' : String(contract.value),
                      starts_at: contract.starts_at,
                      ends_at: contract.ends_at ?? '',
                      billing_period: contract.billing_period ?? '',
                      price_review_years: String(contract.price_review_years ?? 2),
                      signed_at: contract.signed_at ?? '',
                      notes: contract.notes ?? '',
                      pdf: null,
                  }
                : { ...EMPTY },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contract?.id, open]);

    function change<K extends keyof FormData>(field: K, value: FormData[K]) {
        clearErrors(field as string);
        setData(field as string, value);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (contract) {
            /*
             * PUT com arquivo não chega: PHP não lê multipart em PUT. O jeito é
             * POST com _method, que o Inertia converte quando forceFormData está
             * ligado — e ele liga sozinho ao ver um File nos dados.
             */
            post(route('contratos.update', contract.id), {
                method: 'put',
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });

            return;
        }

        // Cadastro direto: sem documento, sem modelo — só os dados do acordo.
        post(route('contratos.registrar'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="border-b p-6 text-left">
                    <div className="space-y-1.5 pr-8">
                        <SheetTitle className="tabular">{contract ? contract.number : 'Novo contrato'}</SheetTitle>
                        <SheetDescription>
                            {contract ? `${contract.client.name} — ${contract.service}` : 'Cadastre um contrato que já vale, sem gerar documento.'}
                        </SheetDescription>
                    </div>
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div className="grid min-h-0 flex-1 content-start gap-5 overflow-y-auto p-6">
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

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Serviço" required error={errors.service}>
                                <Input id="service" value={data.service} onChange={(e) => change('service', e.target.value)} />
                            </Field>

                            <Field label="Valor" error={errors.value}>
                                <CurrencyInput id="value" value={data.value} onChange={(v) => change('value', v)} placeholder="0,00" />
                            </Field>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Início" required error={errors.starts_at}>
                                <Input id="starts_at" type="date" value={data.starts_at} onChange={(e) => change('starts_at', e.target.value)} />
                            </Field>

                            <Field label="Fim" error={errors.ends_at} hint="Em branco: indeterminado.">
                                <Input id="ends_at" type="date" value={data.ends_at} onChange={(e) => change('ends_at', e.target.value)} />
                            </Field>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Período contratado" error={errors.billing_period} hint="Guia a renovação.">
                                <Select value={data.billing_period || undefined} onValueChange={(v) => change('billing_period', v)}>
                                    <SelectTrigger id="billing_period">
                                        <SelectValue placeholder="Não definido" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="monthly">Mensal</SelectItem>
                                        <SelectItem value="annual">Anual</SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>

                            <Field label="Reajustar a cada (anos)" error={errors.price_review_years} hint="A data sai do início + esses anos.">
                                <Input
                                    id="price_review_years"
                                    type="number"
                                    min="1"
                                    max="20"
                                    className="w-24"
                                    value={data.price_review_years}
                                    onChange={(e) => change('price_review_years', e.target.value)}
                                />
                            </Field>
                        </div>

                        {/* Só na edição: um cadastro direto já vale pela data, sem assinatura. */}
                        {contract && (
                            <Field
                                label="Assinado em"
                                error={errors.signed_at}
                                hint="Preenchido, o contrato passa a valer e o texto deixa de ser regerado."
                            >
                                <Input
                                    id="signed_at"
                                    type="date"
                                    className="w-44"
                                    value={data.signed_at}
                                    onChange={(e) => change('signed_at', e.target.value)}
                                />
                            </Field>
                        )}

                        {/* Anexo: é aqui que entra o contrato assinado e digitalizado. */}
                        <div className="grid gap-2">
                            <Label>Contrato assinado (PDF)</Label>

                            {contract?.has_attachment && data.pdf === null ? (
                                <div className="flex items-center gap-2 rounded-lg border px-3 py-2">
                                    <Paperclip className="text-muted-foreground size-4 shrink-0" />
                                    <span className="flex-1 truncate text-sm">Arquivo anexado</span>

                                    <Button type="button" variant="ghost" size="icon-sm" aria-label="Baixar" asChild>
                                        <a href={route('contratos.pdf', contract.id)}>
                                            <Download />
                                        </a>
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Remover arquivo"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => router.delete(route('contratos.arquivo.destroy', contract.id), { preserveScroll: true })}
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2">
                                    <Button type="button" variant="outline" onClick={() => fileInput.current?.click()}>
                                        <Upload />
                                        {data.pdf ? 'Trocar arquivo' : 'Escolher PDF'}
                                    </Button>

                                    {data.pdf && <span className="text-muted-foreground truncate text-sm">{data.pdf.name}</span>}
                                </div>
                            )}

                            <input
                                ref={fileInput}
                                type="file"
                                accept="application/pdf"
                                className="hidden"
                                onChange={(e) => change('pdf', e.target.files?.[0] ?? null)}
                            />

                            {errors.pdf && <p className="text-destructive text-xs font-medium">{errors.pdf}</p>}

                            <p className="text-muted-foreground text-xs">
                                Sem anexo, o download entrega o PDF gerado do texto. Com anexo, entrega o assinado.
                            </p>
                        </div>

                        <Field label="Observações internas" error={errors.notes}>
                            <Textarea id="notes" rows={2} value={data.notes} onChange={(e) => change('notes', e.target.value)} />
                        </Field>

                        {contract?.has_body && (
                            <div className="grid gap-2">
                                <Button type="button" variant="outline" size="sm" className="justify-start" onClick={() => setShowText((v) => !v)}>
                                    <FileText />
                                    {showText ? 'Esconder o texto' : 'Ver o texto do contrato'}
                                </Button>

                                {showText && (
                                    <pre className="bg-muted/40 max-h-72 overflow-y-auto rounded-lg border px-3 py-2 font-sans text-xs leading-relaxed whitespace-pre-wrap">
                                        {contract.body}
                                    </pre>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="bg-muted/30 flex items-center justify-end gap-2 border-t p-6">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>

                        <Button type="submit" loading={processing}>
                            {!processing && <Check />}
                            Salvar
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
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
