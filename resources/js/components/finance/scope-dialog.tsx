import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { CalendarRange, Layers, RefreshCw, Square } from 'lucide-react';
import { useEffect, useState } from 'react';

export type Scope = 'one' | 'forward' | 'all';

interface ScopeDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** 'installments' ou 'recurring' — muda só as palavras. */
    kind: 'installments' | 'recurring';
    action: 'editar' | 'excluir';
    /** Posição na série, quando conhecida: "2 de 12". */
    position?: string | null;
    /** Ao excluir todas as cobranças de uma recorrência, se remove também a recorrência. */
    onConfirm: (scope: Scope, removeSeries: boolean) => void;
}

/**
 * Pergunta o alcance antes de mexer numa conta de série.
 *
 * Começa em "só esta" de propósito: errar para menos se conserta repetindo a
 * ação, errar para mais apaga doze contas que ninguém mandou apagar.
 */
export function ScopeDialog({ open, onOpenChange, kind, action, position, onConfirm }: ScopeDialogProps) {
    const [scope, setScope] = useState<Scope>('one');
    const [removeSeries, setRemoveSeries] = useState(true);

    useEffect(() => {
        if (open) {
            setScope('one');
            setRemoveSeries(true);
        }
    }, [open]);

    const isRecurring = kind === 'recurring';
    const noun = isRecurring ? 'cobrança' : 'parcela';
    const plural = isRecurring ? 'cobranças' : 'parcelas';

    const options: { value: Scope; label: string; hint: string; icon: typeof Square }[] = [
        { value: 'one', label: `Somente esta ${noun}`, hint: 'As outras ficam como estão', icon: Square },
        {
            value: 'forward',
            label: 'Esta e as próximas',
            hint: `O que já passou não muda${action === 'excluir' ? '' : ', inclusive o que já foi pago'}`,
            icon: CalendarRange,
        },
        {
            value: 'all',
            label: `Todas as ${plural}`,
            hint: isRecurring ? 'O contrato inteiro' : 'O parcelamento inteiro',
            icon: isRecurring ? RefreshCw : Layers,
        },
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {action === 'excluir' ? 'Excluir' : 'Salvar'} — o que fazer com as outras {plural}?
                    </DialogTitle>
                    <DialogDescription>
                        Esta conta faz parte {isRecurring ? 'de um contrato que se renova' : 'de um parcelamento'}
                        {position ? ` (${position})` : ''}.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2">
                    {options.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            onClick={() => setScope(option.value)}
                            aria-pressed={scope === option.value}
                            className={cn(
                                'flex items-start gap-3 rounded-lg border px-3 py-2.5 text-left transition-all',
                                'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden',
                                scope === option.value
                                    ? 'border-primary bg-accent text-accent-foreground shadow-xs'
                                    : 'border-input hover:border-primary/30',
                            )}
                        >
                            <option.icon className="mt-0.5 size-4 shrink-0" />
                            <span className="min-w-0">
                                <span className="block text-sm font-medium">{option.label}</span>
                                <span className="text-muted-foreground block text-xs">{option.hint}</span>
                            </span>
                        </button>
                    ))}
                </div>

                {action === 'excluir' && isRecurring && scope === 'all' && (
                    <label className="flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2.5 text-sm">
                        <Checkbox checked={removeSeries} onCheckedChange={(value) => setRemoveSeries(value === true)} />
                        Remover também a recorrência da lista
                    </label>
                )}

                <DialogFooter>
                    <Button variant="secondary" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button variant={action === 'excluir' ? 'destructive' : 'default'} onClick={() => onConfirm(scope, removeSeries)}>
                        {action === 'excluir' ? 'Excluir' : 'Salvar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
