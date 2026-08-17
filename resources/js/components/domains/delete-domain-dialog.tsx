import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Domain } from '@/types/domains';
import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

export function DeleteDomainDialog({ domain, onOpenChange }: { domain: Domain | null; onOpenChange: (open: boolean) => void }) {
    const { delete: destroy, processing } = useForm();

    function confirm() {
        if (!domain) return;

        destroy(route('dominios.destroy', domain.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={domain !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Excluir domínio</DialogTitle>
                    <DialogDescription>
                        <strong className="text-foreground">{domain?.name}</strong> sai do sistema. Isso não cancela o registro no registrador — só
                        deixamos de acompanhar o vencimento.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button variant="destructive" onClick={confirm} loading={processing}>
                        {!processing && <Trash2 />}
                        Excluir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
