import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { Checklist, MaintenancePlan, MaintenanceRecord, MaintenanceResult } from '@/types/maintenance';
import { useForm } from '@inertiajs/react';
import { Check, CircleAlert, MessageCircle } from 'lucide-react';
import { useEffect } from 'react';

interface MaintenanceDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** O plano que está sendo atendido. */
    plan: MaintenancePlan | null;
    /** Preenchida quando se está corrigindo um registro do histórico. */
    record?: MaintenanceRecord | null;
    checklist: Checklist;
}

interface MaintenanceFormData {
    performed_at: string;
    items: Record<string, MaintenanceResult>;
    notes: string;
    notify: boolean;

    [key: string]: string | boolean | Record<string, MaintenanceResult>;
}

/** Tudo realizado é o caso comum: quem executou tira o que não se aplica. */
function allDone(keys: string[]): Record<string, MaintenanceResult> {
    return Object.fromEntries(keys.map((key) => [key, 'done' as MaintenanceResult]));
}

const today = () => new Date().toISOString().slice(0, 10);

export function MaintenanceDialog({ open, onOpenChange, plan, record, checklist }: MaintenanceDialogProps) {
    const isEditing = Boolean(record);

    const { data, setData, post, put, processing, errors, clearErrors } = useForm<MaintenanceFormData>({
        performed_at: today(),
        items: allDone(checklist.items.map((item) => item.key)),
        notes: '',
        notify: false,
    });

    useEffect(() => {
        if (!open) return;

        setData({
            performed_at: record?.performed_at ?? today(),
            items: record
                ? Object.fromEntries(record.items.map((item) => [item.key, item.result]))
                : allDone(checklist.items.map((item) => item.key)),
            notes: record?.notes ?? '',
            // No histórico o relatório já foi (ou falhou) — reenviar é ação própria.
            notify: !record,
        });

        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, record?.id, plan?.id]);

    function setResult(key: string, result: MaintenanceResult) {
        setData('items', { ...data.items, [key]: result });
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        if (record) {
            put(route('manutencao.registros.update', record.id), options);
        } else if (plan) {
            post(route('manutencao.registros.store', plan.id), options);
        }
    }

    const site = plan?.site_url ?? record?.site_url ?? '';
    const clientName = plan?.client.name ?? record?.client.name ?? '';
    const semTelefone = plan !== null && !plan.client.has_phone;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {/*
             * Largura colada no conteúdo: com o diálogo largo demais sobrava um
             * vão enorme entre o nome do item e as opções, e o olho perdia a
             * ligação entre os dois.
             */}
            <DialogContent className="flex max-h-[90vh] max-w-xl flex-col gap-0 p-0">
                <DialogHeader className="border-b px-5 py-4 text-left">
                    <DialogTitle>{isEditing ? 'Editar manutenção' : 'Registrar manutenção'}</DialogTitle>
                    <DialogDescription className="truncate">
                        {clientName} — {site}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                        <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="performed_at">Data da manutenção</Label>
                                {/*
                                 * Largura do conteúdo: num campo largo o ícone do
                                 * calendário nativo para no meio do vazio.
                                 */}
                                <Input
                                    id="performed_at"
                                    type="date"
                                    className="w-40"
                                    value={data.performed_at}
                                    onChange={(e) => setData('performed_at', e.target.value)}
                                />
                            </div>

                            <p className="text-muted-foreground max-w-[17rem] text-xs leading-snug">
                                <span className="text-foreground font-medium">Não necessário</span> vai no relatório;{' '}
                                <span className="text-foreground font-medium">Pular</span> fica pendente e não é enviado.
                            </p>
                        </div>

                        {errors.performed_at && <p className="text-destructive text-xs font-medium">{errors.performed_at}</p>}

                        <div className="divide-y overflow-hidden rounded-lg border">
                            {checklist.items.map((item) => (
                                <div
                                    key={item.key}
                                    className="hover:bg-muted/40 flex items-center justify-between gap-4 px-3 py-1.5 transition-colors"
                                >
                                    <span className={cn('truncate text-sm', data.items[item.key] === 'skipped' && 'text-muted-foreground')}>
                                        {item.label}
                                    </span>

                                    {/* Largura fixa para as três opções ficarem na mesma coluna em todas as linhas. */}
                                    <SegmentedControl
                                        aria-label={item.label}
                                        className="w-60 shrink-0 p-0.5"
                                        value={data.items[item.key] ?? 'done'}
                                        onChange={(value) => setResult(item.key, value as MaintenanceResult)}
                                        options={checklist.results.map((result) => ({
                                            value: result.value,
                                            label: result.label,
                                            activeClassName:
                                                result.value === 'done'
                                                    ? 'text-success'
                                                    : result.value === 'skipped'
                                                      ? 'text-muted-foreground'
                                                      : 'text-foreground',
                                        }))}
                                    />
                                </div>
                            ))}
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="notes">Observações gerais</Label>
                            <Textarea
                                id="notes"
                                rows={2}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder="O que vale o cliente saber (vai no relatório)."
                            />
                            {errors.notes && <p className="text-destructive text-xs font-medium">{errors.notes}</p>}
                        </div>

                        {!isEditing && (
                            <div className="space-y-1.5">
                                <label className="flex cursor-pointer items-center gap-2.5 text-sm">
                                    <Checkbox
                                        checked={data.notify}
                                        onCheckedChange={(checked) => setData('notify', checked === true)}
                                        disabled={semTelefone}
                                    />
                                    <MessageCircle className="text-muted-foreground size-4 shrink-0" />
                                    Enviar o relatório no WhatsApp do cliente
                                </label>

                                {semTelefone && (
                                    <p className="text-muted-foreground flex items-center gap-1.5 pl-6 text-xs">
                                        <CircleAlert className="size-3.5 shrink-0" />
                                        {clientName} não tem telefone cadastrado.
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter className="bg-muted/30 border-t px-5 py-4">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>

                        <Button type="submit" loading={processing}>
                            {!processing && <Check />}
                            {isEditing ? 'Salvar alterações' : 'Salvar manutenção'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
