import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/**
 * Converte a mensagem flash da sessao em toast. Depende do timestamp da visita
 * para que duas acoes iguais seguidas (ex.: excluir dois clientes) disparem
 * dois toasts, e nao um so.
 */
export function useFlashToast() {
    const { flash } = usePage<SharedData>().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        /*
         * Aviso e nao erro: a acao foi feita, so uma parte dela nao. Fica mais
         * tempo na tela porque costuma pedir algo de quem leu — reconectar o
         * WhatsApp, cadastrar um telefone.
         */
        if (flash?.warning) {
            toast.warning(flash.warning, { duration: 8000 });
        }
    }, [flash]);
}
