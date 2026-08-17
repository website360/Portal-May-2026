import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { ClientOption } from '@/types/domains';
import { EMPTY_PLAN_FORM, toPlanFormData, type MaintenancePlan, type PlanFormData } from '@/types/maintenance';
import { useForm } from '@inertiajs/react';
import { Check, Globe } from 'lucide-react';
import { useEffect } from 'react';

interface PlanFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Preenchido em edição; nulo em cadastro. */
    plan: MaintenancePlan | null;
    clients: ClientOption[];
}

export function PlanFormSheet({ open, onOpenChange, plan, clients }: PlanFormSheetProps) {
    const { data, setData, post, put, processing, errors, clearErrors, reset, setDefaults } = useForm<PlanFormData>(EMPTY_PLAN_FORM);

    const isEditing = plan !== null;

    useEffect(() => {
        if (!open) return;

        const values = plan ? toPlanFormData(plan) : { ...EMPTY_PLAN_FORM };

        setDefaults(values);
        reset();
        setData(values);
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, plan?.id]);

    function change<K extends keyof PlanFormData>(field: K, value: PlanFormData[K]) {
        // O erro do servidor vive até a próxima resposta; some ao corrigir o campo.
        clearErrors(field as string);
        setData(field as string, value);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        if (isEditing) {
            put(route('manutencao.planos.update', plan.id), options);
        } else {
            post(route('manutencao.planos.store'), options);
        }
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="border-b p-6 text-left">
                    <div className="space-y-1.5 pr-8">
                        <SheetTitle>{isEditing ? 'Editar plano' : 'Novo plano de manutenção'}</SheetTitle>
                        <SheetDescription>{isEditing ? `Alterando ${plan.site_url}.` : 'O site que a agência revisa todo mês.'}</SheetDescription>
                    </div>
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    {/*
                     * content-start: o grid ocupa a altura toda do painel, e sem
                     * isso as linhas esticam para preencher a sobra — o rótulo
                     * fica no topo e o campo despenca para o meio do vão. Com
                     * poucos campos, como aqui, o formulário se desmonta.
                     */}
                    <div className="grid min-h-0 flex-1 content-start gap-5 overflow-y-auto p-6">
                        <Field label="Cliente" required error={errors.client_id}>
                            <Combobox
                                id="client_id"
                                value={data.client_id}
                                onChange={(value) => change('client_id', value)}
                                options={clients.map((client) => ({ value: String(client.id), label: client.name, search: client.search }))}
                                placeholder="Escolha o cliente"
                                searchPlaceholder="Buscar cliente…"
                                emptyText="Nenhum cliente com esse nome."
                            />
                        </Field>

                        <Field label="Site" required error={errors.site_url}>
                            <Input
                                id="site_url"
                                startIcon={Globe}
                                value={data.site_url}
                                onChange={(e) => change('site_url', e.target.value)}
                                placeholder="www.cliente.com.br"
                            />
                        </Field>

                        <label className="flex cursor-pointer items-start gap-2.5">
                            <Checkbox checked={data.active} onCheckedChange={(checked) => change('active', checked === true)} className="mt-0.5" />
                            <span className="grid gap-0.5">
                                <span className="text-sm font-medium">Plano ativo</span>
                                <span className="text-muted-foreground text-xs">Pausado, o site sai dos avisos e o histórico fica.</span>
                            </span>
                        </label>

                        <Field label="Observações" error={errors.notes}>
                            <Textarea
                                id="notes"
                                rows={3}
                                value={data.notes}
                                onChange={(e) => change('notes', e.target.value)}
                                placeholder="Acesso ao painel, particularidades do site, combinados…"
                            />
                        </Field>
                    </div>

                    <div className="bg-muted/30 flex items-center justify-end gap-2 border-t p-6">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>

                        <Button type="submit" loading={processing}>
                            {!processing && <Check />}
                            {isEditing ? 'Salvar alterações' : 'Criar plano'}
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
        <div className="grid gap-2">
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
