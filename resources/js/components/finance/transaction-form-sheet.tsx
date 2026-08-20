import { ScopeDialog, type Scope } from '@/components/finance/scope-dialog';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { recurrenceIntervals, repeatSegments, transactionTypeSegments } from '@/config/domain';
import { numberToCurrency } from '@/lib/masks';
import { cn } from '@/lib/utils';
import {
    categoriesForType,
    EMPTY_TRANSACTION_FORM,
    toTransactionFormData,
    type CostCenter,
    type FinanceCategory,
    type RepeatMode,
    type Transaction,
    type TransactionFormData,
    type TransactionType,
} from '@/types/finance';
import { useForm } from '@inertiajs/react';
import { Building2, CalendarDays, Check, CircleCheck, CreditCard, Layers, RefreshCw, Tag, Trash2, UserRound, Wallet } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface TransactionFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    transaction: Transaction | null;
    costCenters: CostCenter[];
    categories: FinanceCategory[];
    clients: { id: number; name: string }[];
    paymentMethods: { id: number; name: string }[];
    suppliers: { id: number; name: string; search?: string }[];
}

export function TransactionFormSheet({
    open,
    onOpenChange,
    transaction,
    costCenters,
    categories,
    clients,
    paymentMethods,
    suppliers,
}: TransactionFormSheetProps) {
    const {
        data,
        setData,
        post,
        put,
        delete: destroy,
        processing,
        errors,
        clearErrors,
        reset,
        setDefaults,
        transform,
    } = useForm<TransactionFormData>(EMPTY_TRANSACTION_FORM);

    const isEditing = transaction !== null;
    const isReceivable = data.type === 'receivable';
    const scrollRef = useRef<HTMLDivElement>(null);
    const [scopeAction, setScopeAction] = useState<'editar' | 'excluir' | null>(null);
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const failed = Object.entries(errors).filter(([, message]) => Boolean(message)) as [string, string][];

    // Erro fora da área visível passaria despercebido: sobe para o resumo.
    useEffect(() => {
        if (failed.length > 0) {
            scrollRef.current?.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }, [failed.length]);

    useEffect(() => {
        if (!open) return;

        const values = transaction
            ? toTransactionFormData(transaction)
            : { ...EMPTY_TRANSACTION_FORM, due_date: new Date().toISOString().slice(0, 10) };

        setDefaults(values);
        reset();
        // Zera qualquer transform pendente (ex.: o de excluir, que manda só o
        // `scope`). Sem isto, o próximo envio ia estripado e o backend acusava
        // "campos obrigatórios" com a tela preenchida.
        transform((data) => data);
        setData(values);
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, transaction?.id]);

    /**
     * Grava um campo e apaga o erro dele na hora.
     *
     * Sem isso, o erro do envio anterior fica na tela até o próximo — a pessoa
     * corrige o campo, vê o texto certo preenchido, e a mensagem continua
     * dizendo que está vazio.
     */
    function change<K extends keyof TransactionFormData>(field: K, value: TransactionFormData[K]) {
        setData(field, value);

        if (errors[field as string]) {
            clearErrors(field as string);
        }
    }

    /** Trocar a natureza invalida a categoria escolhida: elas são por tipo. */
    function changeType(type: TransactionType) {
        setData('type', type);
        setData('finance_category_id', '');
    }

    /** Faz parte de parcelamento ou de contrato? Só então perguntamos o alcance. */
    const seriesKind = transaction?.kind === 'once' ? null : (transaction?.kind ?? null);

    function save(scope: Scope = 'one') {
        // transform é o jeito do useForm de acrescentar algo ao envio sem
        // guardar no estado do formulário.
        transform((current) => ({ ...current, scope }));

        put(route('financeiro.update', transaction!.id), {
            preserveScroll: true,
            onSuccess: () => {
                setScopeAction(null);
                onOpenChange(false);
            },
        });
    }

    function remove(scope: Scope = 'one', removeSeries = false) {
        transform(() => ({ scope, remove_recurrence: removeSeries }) as unknown as TransactionFormData);

        destroy(route('financeiro.destroy', transaction!.id), {
            preserveScroll: true,
            onSuccess: () => {
                setScopeAction(null);
                onOpenChange(false);
            },
        });
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (!isEditing) {
            post(route('financeiro.store'), { preserveScroll: true, onSuccess: () => onOpenChange(false) });

            return;
        }

        // Conta de série pergunta antes; avulsa segue direto.
        if (seriesKind) {
            setScopeAction('editar');

            return;
        }

        save();
    }

    const asOptions = <T extends { id: number; name: string; search?: string }>(list: T[]) =>
        list.map((item) => ({ value: String(item.id), label: item.name, search: item.search }));

    /** "12 cobranças mensais" — deixa o total explícito sem a pessoa fazer conta. */
    function intervalWord(interval: string, count: number): string {
        const words: Record<string, [string, string]> = {
            monthly: ['cobrança mensal', 'cobranças mensais'],
            quarterly: ['cobrança trimestral', 'cobranças trimestrais'],
            semiannual: ['cobrança semestral', 'cobranças semestrais'],
            annual: ['cobrança anual', 'cobranças anuais'],
        };

        const [one, many] = words[interval] ?? words.annual;

        return count === 1 ? one : many;
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b px-6 py-4 text-left">
                    <SheetTitle className="text-base">{isEditing ? 'Editar lançamento' : 'Novo lançamento'}</SheetTitle>
                    <SheetDescription className="sr-only">Descrição, valor, vencimento, centro de custo e categoria.</SheetDescription>
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div ref={scrollRef} className="min-h-0 flex-1 overflow-y-auto">
                        {/*
                          Resumo do que faltou, no topo.
                          A mensagem ao lado do campo só ajuda se o campo estiver
                          à vista — numa gaveta que rola, o erro pode acontecer
                          fora da tela e o botão parece simplesmente não fazer
                          nada, ou acusar um campo que a pessoa jura ter preenchido.
                        */}
                        {failed.length > 0 && (
                            <div className="border-destructive/30 bg-destructive/5 mx-6 mt-4 rounded-lg border px-4 py-3">
                                <p className="text-destructive text-sm font-medium">
                                    {failed.length === 1 ? 'Falta corrigir um campo:' : `Faltam corrigir ${failed.length} campos:`}
                                </p>
                                <ul className="text-destructive/90 mt-1 space-y-0.5 text-xs">
                                    {failed.map(([field, message]) => (
                                        <li key={field}>
                                            <strong>{FIELD_LABELS[field] ?? field}</strong> — {message}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="space-y-3 px-6 pt-5 pb-3">
                            <SegmentedControl
                                value={data.type}
                                onChange={(value) => changeType(value as TransactionType)}
                                options={transactionTypeSegments}
                                aria-label="Tipo de lançamento"
                                className="max-w-72"
                            />

                            <div>
                                <Textarea
                                    id="description"
                                    autoFocus
                                    rows={1}
                                    value={data.description}
                                    onChange={(e) => change('description', e.target.value)}
                                    placeholder={isReceivable ? 'Do que é esse recebimento?' : 'Do que é essa conta?'}
                                    className="min-h-0 resize-none border-0 bg-transparent px-0 py-0 text-xl leading-snug font-semibold shadow-none focus-visible:ring-0"
                                />
                                {errors.description && <p className="text-destructive text-xs font-medium">{errors.description}</p>}
                            </div>
                        </div>

                        <div className="space-y-1 border-t px-6 py-4">
                            <Property icon={Wallet} label="Valor" required error={errors.amount}>
                                <div className="max-w-48">
                                    <CurrencyInput id="amount" value={data.amount} onChange={(value) => change('amount', value)} placeholder="0,00" />
                                </div>
                                {!isEditing && data.repeat === 'installments' && (
                                    <p className="text-muted-foreground mt-1 text-xs">Valor total da dívida — será dividido nas parcelas.</p>
                                )}
                            </Property>

                            <Property icon={CalendarDays} label="Vencimento" required error={errors.due_date}>
                                <Input
                                    id="due_date"
                                    type="date"
                                    className="max-w-48"
                                    value={data.due_date}
                                    onChange={(e) => change('due_date', e.target.value)}
                                />
                            </Property>

                            <Property icon={Building2} label="Centro de custo" required error={errors.cost_center_id}>
                                <Combobox
                                    id="cost_center_id"
                                    value={data.cost_center_id}
                                    onChange={(value) => change('cost_center_id', value)}
                                    options={asOptions(costCenters)}
                                    placeholder="Escolha o centro"
                                    searchPlaceholder="Buscar centro…"
                                    emptyText="Nenhum centro com esse nome."
                                />
                            </Property>

                            <Property icon={Tag} label="Categoria" error={errors.finance_category_id}>
                                <Combobox
                                    id="finance_category_id"
                                    value={data.finance_category_id}
                                    onChange={(value) => change('finance_category_id', value)}
                                    options={asOptions(categoriesForType(categories, data.type))}
                                    placeholder="Sem categoria"
                                    searchPlaceholder="Buscar categoria…"
                                    emptyText="Nenhuma categoria dessa natureza."
                                    clearable
                                    clearLabel="Sem categoria"
                                />
                            </Property>

                            {isReceivable ? (
                                <Property icon={UserRound} label="Cliente" error={errors.client_id}>
                                    <Combobox
                                        id="client_id"
                                        value={data.client_id}
                                        onChange={(value) => change('client_id', value)}
                                        options={asOptions(clients)}
                                        placeholder="Nenhum"
                                        searchPlaceholder="Buscar cliente…"
                                        emptyText="Nenhum cliente com esse nome."
                                        clearable
                                        clearLabel="Nenhum"
                                    />
                                </Property>
                            ) : (
                                <Property icon={UserRound} label="Fornecedor" error={errors.supplier_id}>
                                    <Combobox
                                        id="supplier_id"
                                        value={data.supplier_id}
                                        onChange={(value) => change('supplier_id', value)}
                                        options={asOptions(suppliers)}
                                        placeholder="Sem fornecedor"
                                        searchPlaceholder="Buscar fornecedor…"
                                        emptyText="Nenhum cadastrado. Cadastre em Configurações."
                                        clearable
                                        clearLabel="Sem fornecedor"
                                    />
                                </Property>
                            )}

                            <Property icon={CreditCard} label="Forma" error={errors.payment_method_id}>
                                <Combobox
                                    id="payment_method_id"
                                    value={data.payment_method_id}
                                    onChange={(value) => change('payment_method_id', value)}
                                    options={asOptions(paymentMethods)}
                                    placeholder="Sem forma definida"
                                    searchPlaceholder="Buscar forma…"
                                    emptyText="Nenhuma forma cadastrada. Cadastre em Configurações."
                                    clearable
                                    clearLabel="Sem forma definida"
                                    className="max-w-56"
                                />
                            </Property>

                            {!isEditing && (
                                <>
                                    <Property icon={Layers} label="Repetição" error={errors.repeat}>
                                        <SegmentedControl
                                            value={data.repeat}
                                            onChange={(value) => setData('repeat', value as RepeatMode)}
                                            options={repeatSegments}
                                            aria-label="Repetição"
                                        />
                                    </Property>

                                    {/*
                                      A diferença que a pessoa precisa entender na hora de
                                      escolher: parcelado fatia uma dívida que já existe
                                      inteira; recorrente é compromisso que renova, e por
                                      isso avisa quando está para acabar.
                                    */}
                                    {data.repeat === 'installments' && (
                                        <Property icon={Layers} label="Parcelas" error={errors.installments}>
                                            <div className="flex items-center gap-3">
                                                <Input
                                                    id="installments"
                                                    type="number"
                                                    min="2"
                                                    max="36"
                                                    className="w-24"
                                                    value={data.installments}
                                                    onChange={(e) => change('installments', e.target.value)}
                                                />
                                                <span className="text-muted-foreground text-xs">{installmentPreview(data.amount, data.installments)}</span>
                                            </div>
                                        </Property>
                                    )}

                                    {data.repeat === 'recurring' && (
                                        <>
                                            <Property icon={RefreshCw} label="A cada" required error={errors.interval}>
                                                <Combobox
                                                    id="interval"
                                                    value={data.interval}
                                                    onChange={(value) => change('interval', value)}
                                                    options={recurrenceIntervals}
                                                    placeholder="Escolha o intervalo"
                                                    searchPlaceholder="Buscar…"
                                                    className="max-w-56"
                                                />
                                            </Property>

                                            <Property icon={Layers} label="Cobranças" error={errors.occurrences}>
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <Input
                                                        id="occurrences"
                                                        type="number"
                                                        min="1"
                                                        max="600"
                                                        className="w-24"
                                                        value={data.occurrences}
                                                        onChange={(e) => change('occurrences', e.target.value)}
                                                        placeholder="12"
                                                    />
                                                    <span className="text-muted-foreground text-xs">
                                                        {data.occurrences
                                                            ? `${data.occurrences} ${intervalWord(data.interval, Number(data.occurrences))}. Avisamos na última, para dar tempo de renovar.`
                                                            : 'Em branco: corre até você encerrar, e não há aviso de última cobrança.'}
                                                    </span>
                                                </div>
                                            </Property>
                                        </>
                                    )}
                                </>
                            )}

                            <Property icon={CircleCheck} label="Pagamento" error={errors.paid_at ?? errors.paid_amount}>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Input
                                        id="paid_at"
                                        type="date"
                                        className="max-w-44"
                                        value={data.paid_at}
                                        onChange={(e) => change('paid_at', e.target.value)}
                                        aria-label="Data do pagamento"
                                    />

                                    {data.paid_at && (
                                        <div className="w-36">
                                            <CurrencyInput
                                                id="paid_amount"
                                                value={data.paid_amount}
                                                onChange={(value) => change('paid_amount', value)}
                                                placeholder={numberToCurrency(data.amount) || '0,00'}
                                                aria-label="Valor pago"
                                            />
                                        </div>
                                    )}
                                </div>
                                {!data.paid_at && <p className="text-muted-foreground mt-1 text-xs">Deixe vazio enquanto estiver em aberto.</p>}
                            </Property>

                            <Property icon={Layers} label="Observações" error={errors.notes}>
                                <Textarea
                                    id="notes"
                                    rows={2}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    placeholder="Número do boleto, combinado com o fornecedor…"
                                />
                            </Property>
                        </div>
                    </div>

                    <div className="bg-muted/30 flex items-center justify-between gap-3 border-t px-6 py-4">
                        {isEditing ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                onClick={() => (seriesKind ? setScopeAction('excluir') : setConfirmingDelete(true))}
                            >
                                <Trash2 />
                                Excluir
                            </Button>
                        ) : (
                            <span />
                        )}

                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                                Cancelar
                            </Button>

                            <Button type="submit" loading={processing}>
                                {!processing && <Check />}
                                {isEditing ? 'Salvar' : 'Criar lançamento'}
                            </Button>
                        </div>
                    </div>
                </form>
            </SheetContent>
            {/* Avulso confirma direto; série pergunta o alcance. */}
            <ConfirmDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                title={`Excluir "${transaction?.description ?? ''}"?`}
                description="O lançamento sai do financeiro para sempre."
                onConfirm={() => {
                    setConfirmingDelete(false);
                    remove();
                }}
            />

            {seriesKind && (
                <ScopeDialog
                    open={scopeAction !== null}
                    onOpenChange={(value) => !value && setScopeAction(null)}
                    kind={seriesKind}
                    action={scopeAction ?? 'editar'}
                    position={
                        transaction?.installment && transaction?.installments ? `${transaction.installment} de ${transaction.installments}` : null
                    }
                    onConfirm={(scope, removeSeries) => (scopeAction === 'excluir' ? remove(scope, removeSeries) : save(scope))}
                />
            )}
        </Sheet>
    );
}

function Property({
    icon: Icon,
    label,
    required,
    error,
    children,
}: {
    icon: typeof Wallet;
    label: string;
    required?: boolean;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={cn('grid grid-cols-[9rem_1fr] items-center gap-3 py-1.5', error && 'items-start')}>
            <span className="text-muted-foreground flex items-center gap-2 text-sm">
                <Icon className="size-4 shrink-0" />
                {label}
                {required && <span className="text-destructive">*</span>}
            </span>

            <div className="min-w-0">
                {children}
                {error && <p className="text-destructive mt-1 text-xs font-medium">{error}</p>}
            </div>
        </div>
    );
}

/**
 * A prévia do rateio: o valor total vira N parcelas. Os centavos que sobram na
 * divisão vão nas primeiras — a mesma conta do backend, para a tela não
 * prometer um valor que não sai.
 */
function installmentPreview(amountStr: string, countStr: string): string {
    const total = Number(amountStr) || 0;
    const n = Number(countStr) || 0;
    const brl = (value: number) => `R$ ${numberToCurrency(value)}`;

    if (total <= 0 || n < 2) {
        return 'Cria as contas mês a mês, com o valor total dividido entre elas.';
    }

    const totalCents = Math.round(total * 100);
    const baseCents = Math.floor(totalCents / n);
    const remainder = totalCents - baseCents * n;

    if (remainder === 0) {
        return `${n}x de ${brl(baseCents / 100)} — mês a mês, total ${brl(totalCents / 100)}.`;
    }

    const firstLabel = remainder === 1 ? 'a 1ª' : `as ${remainder} primeiras`;
    return `${n}x — ${firstLabel} de ${brl((baseCents + 1) / 100)}, as demais de ${brl(baseCents / 100)} (total ${brl(totalCents / 100)}).`;
}

/** Nome de cada campo em português, para o resumo de erros dizer algo útil. */
const FIELD_LABELS: Record<string, string> = {
    type: 'Tipo',
    description: 'Descrição',
    amount: 'Valor',
    due_date: 'Vencimento',
    cost_center_id: 'Centro de custo',
    finance_category_id: 'Categoria',
    client_id: 'Cliente',
    counterpart: 'Fornecedor ou pagador',
    payment_method_id: 'Forma de pagamento',
    supplier_id: 'Fornecedor',
    notes: 'Observações',
    paid_at: 'Data do pagamento',
    paid_amount: 'Valor pago',
    installments: 'Parcelas',
    interval: 'Repete a cada',
    occurrences: 'Quantidade de cobranças',
    permissao: 'Permissão',
};
