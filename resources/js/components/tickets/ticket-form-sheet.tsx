import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import type { ClientOption } from '@/types/domains';
import { ticketPriorityLabels, ticketPriorityOrder, type AgentOption } from '@/types/tickets';
import { useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect } from 'react';

interface TicketFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    clients: ClientOption[];
    agents: AgentOption[];
}

export function TicketFormSheet({ open, onOpenChange, clients, agents }: TicketFormSheetProps) {
    const { data, setData, post, processing, errors, clearErrors, reset } = useForm({
        subject: '',
        body: '',
        client_id: '',
        assignee_id: '',
        priority: 'normal',
        category: '',
    });

    useEffect(() => {
        if (open) {
            reset();
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function change(field: keyof typeof data, value: string) {
        clearErrors(field);
        setData(field, value);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        // O controller redireciona para o ticket criado; o Inertia navega sozinho.
        post(route('tickets.store'), { preserveScroll: true, onSuccess: () => onOpenChange(false) });
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="border-b p-6 text-left">
                    <SheetTitle>Novo ticket</SheetTitle>
                    <SheetDescription>Abra um atendimento e comece a conversa.</SheetDescription>
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div className="grid min-h-0 flex-1 content-start gap-5 overflow-y-auto p-6">
                        <Field label="Assunto" required error={errors.subject}>
                            <Input id="subject" value={data.subject} onChange={(e) => change('subject', e.target.value)} placeholder="Resumo do problema" />
                        </Field>

                        <Field label="Descrição" required error={errors.body}>
                            <Textarea id="body" rows={5} value={data.body} onChange={(e) => change('body', e.target.value)} placeholder="Conte o que aconteceu…" />
                        </Field>

                        <Field label="Cliente" error={errors.client_id} hint="Opcional — vincule ao cliente do ticket.">
                            <Combobox
                                id="client_id"
                                value={data.client_id}
                                onChange={(value) => change('client_id', value)}
                                options={clients.map((c) => ({ value: String(c.id), label: c.name, search: c.search }))}
                                placeholder="Sem cliente"
                                searchPlaceholder="Buscar cliente…"
                                emptyText="Nenhum cliente com esse nome."
                            />
                        </Field>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Prioridade" error={errors.priority}>
                                <Select value={data.priority} onValueChange={(v) => change('priority', v)}>
                                    <SelectTrigger id="priority">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ticketPriorityOrder.map((p) => (
                                            <SelectItem key={p} value={p}>
                                                {ticketPriorityLabels[p]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>

                            <Field label="Responsável" error={errors.assignee_id}>
                                <Select value={data.assignee_id || undefined} onValueChange={(v) => change('assignee_id', v)}>
                                    <SelectTrigger id="assignee_id">
                                        <SelectValue placeholder="Sem responsável" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {agents.map((a) => (
                                            <SelectItem key={a.value} value={a.value}>
                                                {a.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        </div>

                        <Field label="Categoria" error={errors.category} hint="Ex.: Suporte, Financeiro, Bug.">
                            <Input id="category" value={data.category} onChange={(e) => change('category', e.target.value)} />
                        </Field>
                    </div>

                    <div className="bg-muted/30 flex items-center justify-end gap-2 border-t p-6">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={processing}>
                            {!processing && <Check />}
                            Abrir ticket
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
