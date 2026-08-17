import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { EMPTY_DOMAIN_FORM, toDomainFormData, type ClientOption, type Domain, type DomainFormData } from '@/types/domains';
import { useForm } from '@inertiajs/react';
import { Check, Globe } from 'lucide-react';
import { useEffect } from 'react';

interface DomainFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Preenchido em edição; nulo em cadastro. */
    domain: Domain | null;
    clients: ClientOption[];
    /** Abrindo pela página de um cliente: já vem escolhido e travado. */
    lockedClientId?: number;
}

export function DomainFormSheet({ open, onOpenChange, domain, clients, lockedClientId }: DomainFormSheetProps) {
    const { data, setData, post, put, processing, errors, clearErrors, reset, setDefaults } = useForm<DomainFormData>(EMPTY_DOMAIN_FORM);

    const isEditing = domain !== null;

    useEffect(() => {
        if (!open) return;

        const values = domain ? toDomainFormData(domain) : { ...EMPTY_DOMAIN_FORM, client_id: lockedClientId ? String(lockedClientId) : '' };

        setDefaults(values);
        reset();
        setData(values);
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, domain?.id, lockedClientId]);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        if (isEditing) {
            put(route('dominios.update', domain.id), options);
        } else {
            post(route('dominios.store'), options);
        }
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="border-b p-6 text-left">
                    <div className="space-y-1.5 pr-8">
                        <SheetTitle>{isEditing ? 'Editar domínio' : 'Novo domínio'}</SheetTitle>
                        <SheetDescription>
                            {isEditing ? `Alterando ${domain.name}.` : 'Vincule o domínio a um cliente e defina quem renova.'}
                        </SheetDescription>
                    </div>
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    {/* content-start: sem isso as linhas esticam para preencher o painel e afastam rótulo do campo. */}
                    <div className="grid min-h-0 flex-1 content-start gap-5 overflow-y-auto p-6">
                        <Field label="Cliente" required error={errors.client_id}>
                            <Combobox
                                id="client_id"
                                value={data.client_id}
                                onChange={(value) => setData('client_id', value)}
                                options={clients.map((client) => ({ value: String(client.id), label: client.name, search: client.search }))}
                                placeholder="Escolha o cliente"
                                searchPlaceholder="Buscar cliente…"
                                emptyText="Nenhum cliente com esse nome."
                                disabled={lockedClientId !== undefined}
                            />
                        </Field>

                        <Field label="Domínio" required error={errors.name}>
                            <Input
                                id="name"
                                autoFocus
                                startIcon={Globe}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="agenciamay.com.br"
                            />
                        </Field>

                        <Field label="Quem renova" required error={errors.managed_by}>
                            <div className="grid grid-cols-2 gap-2">
                                <ManagementOption
                                    active={data.managed_by === 'agency'}
                                    label="A agência"
                                    hint="Entra nos avisos de vencimento"
                                    onClick={() => setData('managed_by', 'agency')}
                                />
                                <ManagementOption
                                    active={data.managed_by === 'client'}
                                    label="O cliente"
                                    hint="Só registro, sem aviso"
                                    onClick={() => setData('managed_by', 'client')}
                                />
                            </div>
                        </Field>

                        <Field label="Registrador" error={errors.registrar}>
                            <Input
                                id="registrar"
                                value={data.registrar}
                                onChange={(e) => setData('registrar', e.target.value)}
                                placeholder="Registro.br"
                            />
                        </Field>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Registrado em" error={errors.registered_at}>
                                <Input
                                    id="registered_at"
                                    type="date"
                                    value={data.registered_at}
                                    onChange={(e) => setData('registered_at', e.target.value)}
                                />
                            </Field>

                            <Field label="Vence em" error={errors.expires_at}>
                                <Input id="expires_at" type="date" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} />
                            </Field>
                        </div>

                        <Field label="Custo anual" error={errors.annual_cost}>
                            <CurrencyInput
                                id="annual_cost"
                                value={data.annual_cost}
                                onChange={(value) => setData('annual_cost', value)}
                                placeholder="0,00"
                            />
                        </Field>

                        <Field label="Observações" error={errors.notes}>
                            <Textarea
                                id="notes"
                                rows={3}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder="Login do painel, quem paga, combinados…"
                            />
                        </Field>
                    </div>

                    <div className="bg-muted/30 flex items-center justify-end gap-2 border-t p-6">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>

                        <Button type="submit" loading={processing}>
                            {!processing && <Check />}
                            {isEditing ? 'Salvar alterações' : 'Cadastrar domínio'}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function Field({ label, required, error, children }: { label: string; required?: boolean; error?: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>
                {label}
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            {children}
            {error && <p className="text-destructive text-xs font-medium">{error}</p>}
        </div>
    );
}

function ManagementOption({ active, label, hint, onClick }: { active: boolean; label: string; hint: string; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex flex-col items-start gap-0.5 rounded-lg border px-3 py-2.5 text-left transition-all duration-150',
                'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden active:scale-[0.98]',
                active ? 'border-primary bg-accent text-accent-foreground shadow-xs' : 'border-input bg-background hover:border-primary/30',
            )}
        >
            <span className="text-sm font-medium">{label}</span>
            <span className="text-muted-foreground text-xs">{hint}</span>
        </button>
    );
}
