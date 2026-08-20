import { Button } from '@/components/ui/button';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Contract } from '@/types/contracts';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

/** Sugere a data de fim um ano à frente da atual — o ajuste fino fica com quem renova. */
function oneYearLater(date: string | null): string {
    if (!date) return '';

    const [year, month, day] = date.split('-');

    return `${Number(year) + 1}-${month}-${day}`;
}

export function ContractRenewDialog({ contract, onClose }: { contract: Contract | null; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ ends_at: '', value: '' });

    // Abre já com a sugestão de nova data e o valor atual — renovar é a hora de reajustar.
    useEffect(() => {
        if (contract) {
            setData({
                ends_at: oneYearLater(contract.ends_at),
                value: contract.value === null ? '' : String(contract.value),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contract?.id]);

    return (
        <Dialog open={contract !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Renovar o contrato {contract?.number}</DialogTitle>
                    <DialogDescription>
                        Estende a vigência do mesmo contrato. O cliente, o serviço e o histórico continuam — muda a data de fim e, se quiser, o valor.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="renew-contract"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (!contract) return;

                        post(route('contratos.renovacao', contract.id), {
                            preserveScroll: true,
                            onSuccess: () => {
                                reset();
                                onClose();
                            },
                        });
                    }}
                    className="grid gap-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="renew-ends-at">Nova data de fim</Label>
                        <Input
                            id="renew-ends-at"
                            type="date"
                            className="w-44"
                            value={data.ends_at}
                            onChange={(e) => setData('ends_at', e.target.value)}
                        />
                        {errors.ends_at && <p className="text-destructive text-xs font-medium">{errors.ends_at}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="renew-value">Valor (opcional)</Label>
                        <div className="max-w-48">
                            <CurrencyInput id="renew-value" value={data.value} onChange={(value) => setData('value', value)} placeholder="0,00" />
                        </div>
                        {errors.value && <p className="text-destructive text-xs font-medium">{errors.value}</p>}
                    </div>
                </form>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" form="renew-contract" loading={processing}>
                        Renovar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
