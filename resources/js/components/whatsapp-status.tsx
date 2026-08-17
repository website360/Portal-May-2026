import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { SharedData, WhatsappStatus } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Loader2, MessageCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

const tons: Record<WhatsappStatus['status'], { cor: string; texto: string }> = {
    connected: { cor: 'bg-success', texto: 'WhatsApp conectado' },
    connecting: { cor: 'bg-warning', texto: 'WhatsApp conectando' },
    disconnected: { cor: 'bg-destructive', texto: 'WhatsApp desconectado' },
};

/**
 * A situação do WhatsApp no pé do menu.
 *
 * O que chega do servidor é o último estado gravado — consultar a Evolution a
 * cada página deixaria o sistema inteiro esperando por um serviço de terceiro.
 * Quando esse dado está velho, esta tela pergunta uma vez, em segundo plano, e
 * atualiza sozinha: o custo fica numa requisição isolada, não em toda navegação.
 */
export function WhatsappStatusIndicator() {
    const { whatsapp } = usePage<SharedData>().props;
    const { state } = useSidebar();

    const [status, setStatus] = useState(whatsapp?.status ?? 'disconnected');
    const [quando, setQuando] = useState(whatsapp?.checked_at ?? null);
    const [conferindo, setConferindo] = useState(false);

    useEffect(() => {
        if (!whatsapp?.configured || !whatsapp.stale) return;

        let ativo = true;
        setConferindo(true);

        fetch(route('configuracoes.whatsapp.estado'), { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((dados) => {
                if (!ativo || !dados) return;
                setStatus(dados.status);
                // Acabou de perguntar: dizer "agora mesmo" é mais exato — e evita
                // misturar o formato do servidor com o "há 5 minutos" da carga.
                setQuando('agora mesmo');
            })
            // Falhar aqui é silencioso de propósito: um indicador não pode virar
            // erro na tela de quem só queria abrir o dashboard.
            .catch(() => {})
            .finally(() => ativo && setConferindo(false));

        return () => {
            ativo = false;
        };
    }, [whatsapp?.configured, whatsapp?.stale]);

    // Sem conexão cadastrada não há o que informar — o convite é configurar.
    if (!whatsapp) return null;

    const tom = tons[status] ?? tons.disconnected;
    const recolhido = state === 'collapsed';

    const legenda = !whatsapp.configured
        ? 'WhatsApp não configurado'
        : conferindo
          ? 'Verificando o WhatsApp…'
          : `${tom.texto}${quando ? ` · ${quando}` : ''}`;

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild tooltip={legenda} className="text-muted-foreground hover:text-foreground">
                    <Link href="/configuracoes/whatsapp" prefetch>
                        {/* Ponto colorido: legível de relance, e sobrevive ao menu recolhido. */}
                        <span className="relative flex size-4 shrink-0 items-center justify-center">
                            <MessageCircle className="size-4" />
                            <span
                                className={cn(
                                    'border-sidebar absolute -right-0.5 -bottom-0.5 size-2 rounded-full border',
                                    whatsapp.configured ? tom.cor : 'bg-muted-foreground',
                                )}
                            />
                        </span>

                        {!recolhido && (
                            <span className="flex min-w-0 flex-1 items-center gap-1.5">
                                <span className="truncate text-xs">
                                    {whatsapp.configured ? tom.texto.replace('WhatsApp ', 'WhatsApp: ') : 'WhatsApp não configurado'}
                                </span>
                                {conferindo && <Loader2 className="size-3 shrink-0 animate-spin" />}
                            </span>
                        )}
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
