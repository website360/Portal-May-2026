import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Client } from '@/types/clients';
import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

interface DeleteClientDialogProps {
    client: Client | null;
    onOpenChange: (open: boolean) => void;
}

export function DeleteClientDialog({ client, onOpenChange }: DeleteClientDialogProps) {
    const { delete: destroy, processing } = useForm();

    function confirm() {
        if (!client) return;

        destroy(route('clientes.destroy', client.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={client !== null} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Excluir cliente</DialogTitle>
                    <DialogDescription>
                        {client?.projects_count ? (
                            <>
                                <strong className="text-foreground">{client.name}</strong> tem {client.projects_count}{' '}
                                {client.projects_count === 1
                                    ? 'projeto vinculado, que também será excluído'
                                    : 'projetos vinculados, que também serão excluídos'}
                                . Esta ação não pode ser desfeita.
                            </>
                        ) : (
                            <>
                                <strong className="text-foreground">{client?.name}</strong> será removido definitivamente. Esta ação não pode ser
                                desfeita.
                            </>
                        )}
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
