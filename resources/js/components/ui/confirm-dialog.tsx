import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    /** O que acontece de fato — inclusive o que NÃO é apagado junto. */
    description?: string;
    confirmLabel?: string;
    destructive?: boolean;
    onConfirm: () => void;
}

/**
 * Confirmação para o que não dá para desfazer.
 *
 * O texto diz o efeito, não "tem certeza?": quem lê "as tarefas continuam
 * registradas" decide com informação, e não no susto.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Excluir',
    destructive = true,
    onConfirm,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>

                <DialogFooter>
                    <Button variant="secondary" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button variant={destructive ? 'destructive' : 'default'} onClick={onConfirm}>
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

interface DeleteButtonProps {
    /** Rota do DELETE. */
    url: string;
    title: string;
    description?: string;
    label: string;
    disabled?: boolean;
    className?: string;
    children: React.ReactNode;
}

/**
 * Botão de excluir que já pergunta antes.
 *
 * Encapsular os dois juntos é o que garante que nenhuma tela nova saia por aí
 * apagando direto: quem escrever `<DeleteButton>` ganha a confirmação de graça,
 * e quem escrever `router.delete` solto fica visível na revisão.
 */
export function DeleteButton({ url, title, description, label, disabled, className, children }: DeleteButtonProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                disabled={disabled}
                aria-label={label}
                title={label}
                onClick={() => setOpen(true)}
                className={className}
            >
                {children}
            </Button>

            <ConfirmDialog
                open={open}
                onOpenChange={setOpen}
                title={title}
                description={description}
                onConfirm={() => router.delete(url, { preserveScroll: true, onFinish: () => setOpen(false) })}
            />
        </>
    );
}

interface Pending {
    url: string;
    title: string;
    description?: string;
}

/**
 * Confirmação para listas com muitas linhas.
 *
 * Renderizar um diálogo por linha custaria caro numa lista de cem itens — aqui
 * um só serve a todas, guardando apenas qual está em jogo.
 */
export function useDeleteConfirm() {
    const [pending, setPending] = useState<Pending | null>(null);

    return {
        /** Chame no onClick do botão de excluir. */
        ask: (item: Pending) => setPending(item),
        dialog: (
            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(value) => !value && setPending(null)}
                title={pending?.title ?? ''}
                description={pending?.description}
                onConfirm={() => {
                    router.delete(pending!.url, { preserveScroll: true, onFinish: () => setPending(null) });
                }}
            />
        ),
    };
}
