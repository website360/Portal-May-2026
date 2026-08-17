import { Button } from '@/components/ui/button';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface SettleDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Rota PATCH da situação. */
    url: string;
    description: string;
    /** Valor da conta, que vira o padrão do valor pago. */
    amount: number;
    isReceivable: boolean;
}

const hoje = () => new Date().toISOString().slice(0, 10);

/**
 * Pergunta quando e por quanto a conta foi baixada.
 *
 * A data de hoje é só o padrão: lançar no sistema raramente acontece no mesmo
 * dia em que o dinheiro andou, e assumir hoje jogaria a conta no mês errado —
 * bagunçando "pago no mês" sem ninguém perceber.
 */
export function SettleDialog({ open, onOpenChange, url, description, amount, isReceivable }: SettleDialogProps) {
    const [paidAt, setPaidAt] = useState(hoje);
    const [paidAmount, setPaidAmount] = useState(String(amount));
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            setPaidAt(hoje());
            setPaidAmount(String(amount));
        }
    }, [open, amount]);

    const verbo = isReceivable ? 'Recebimento' : 'Pagamento';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {verbo} de {description}
                    </DialogTitle>
                    <DialogDescription>Quando e por quanto — o valor pode ter mudado com juros ou desconto.</DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="settle-date">Data {isReceivable ? 'do recebimento' : 'do pagamento'}</Label>
                        <Input id="settle-date" type="date" value={paidAt} onChange={(e) => setPaidAt(e.target.value)} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="settle-amount">Valor {isReceivable ? 'recebido' : 'pago'}</Label>
                        <CurrencyInput id="settle-amount" value={paidAmount} onChange={setPaidAmount} />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="secondary" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button
                        loading={saving}
                        disabled={paidAt === ''}
                        onClick={() => {
                            setSaving(true);

                            router.patch(
                                url,
                                { status: 'paid', paid_at: paidAt, paid_amount: paidAmount },
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    onFinish: () => {
                                        setSaving(false);
                                        onOpenChange(false);
                                    },
                                },
                            );
                        }}
                    >
                        Confirmar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
