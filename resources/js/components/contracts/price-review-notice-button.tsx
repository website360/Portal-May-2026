import { Button } from '@/components/ui/button';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { BellRing, Check, Mail } from 'lucide-react';
import { useEffect, useState } from 'react';

/** O mínimo que o botão precisa saber do contrato, para servir na lista e no Dashboard. */
export interface PriceReviewContract {
    id: number;
    number: string;
    value: number | null;
    price_review_label: string | null;
    review_notified: boolean;
    review_notified_label: string | null;
    price_review_new_value: number | null;
}

interface PriceReviewNoticeButtonProps {
    contract: PriceReviewContract;
    variant?: React.ComponentProps<typeof Button>['variant'];
    size?: React.ComponentProps<typeof Button>['size'];
    className?: string;
}

export function PriceReviewNoticeButton({ contract, variant = 'outline', size = 'sm', className }: PriceReviewNoticeButtonProps) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, clearErrors } = useForm({ new_value: '' });

    useEffect(() => {
        if (open) {
            // Começa do valor já enviado (reenvio) ou do valor atual, para você só ajustar.
            const initial = contract.price_review_new_value ?? contract.value ?? 0;
            setData('new_value', String(initial));
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const current = contract.value ?? 0;
    const next = Number(data.new_value) || 0;
    const pct = current > 0 ? ((next - current) / current) * 100 : 0;
    const changed = next > 0 && current > 0 && next !== current;

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(route('contratos.reajuste.aviso', contract.id), { preserveScroll: true, onSuccess: () => setOpen(false) });
    }

    return (
        <>
            <Button
                type="button"
                variant={contract.review_notified ? 'ghost' : variant}
                size={size}
                className={className}
                onClick={() => setOpen(true)}
            >
                {contract.review_notified ? <Check /> : <BellRing />}
                {contract.review_notified ? `Avisado${contract.review_notified_label ? ` ${contract.review_notified_label}` : ''}` : 'Avisar reajuste'}
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-md">
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>Avisar reajuste — contrato {contract.number}</DialogTitle>
                            <DialogDescription>
                                Envia um e-mail ao cliente com o novo valor.
                                {contract.price_review_label ? ` Reajuste a partir de ${contract.price_review_label}.` : ''}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            <div className="bg-muted/40 flex items-center justify-between rounded-lg border px-3 py-2 text-sm">
                                <span className="text-muted-foreground">Valor atual</span>
                                <span className="tabular font-medium">{formatMoney(current)}</span>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="new_value">Novo valor</Label>
                                <CurrencyInput
                                    id="new_value"
                                    value={data.new_value}
                                    onChange={(v) => {
                                        clearErrors('new_value');
                                        setData('new_value', v);
                                    }}
                                />
                                {errors.new_value && <p className="text-destructive text-xs font-medium">{errors.new_value}</p>}
                                {changed && (
                                    <p className="text-muted-foreground text-xs">
                                        {next > current ? 'Aumento' : 'Redução'} de{' '}
                                        <span className={next > current ? 'text-warning font-semibold' : 'font-semibold'}>
                                            {Math.abs(pct).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%
                                        </span>{' '}
                                        ({formatMoney(current)} → {formatMoney(next)})
                                    </p>
                                )}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" loading={processing} disabled={next <= 0}>
                                {!processing && <Mail />}
                                Enviar aviso
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
