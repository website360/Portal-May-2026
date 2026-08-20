import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { activeNavHref, type NavLink } from '@/config/navigation';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items }: { items: NavLink[] }) {
    const page = usePage();
    const activeHref = activeNavHref(items, page.url);

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu className="gap-1">
                {items.map((item) => (
                    <SidebarMenuItem key={item.href}>
                        <SidebarMenuButton
                            asChild
                            isActive={item.href === activeHref}
                            tooltip={item.title}
                            /*
                             * Tamanho padrao do componente: 34px com text-sm.
                             * Tentei 42px e depois 38px; os dois ficaram pesados
                             * para uma lista de cinco itens.
                             *
                             * Fica so a folga entre icone e rotulo, que ajuda a
                             * leitura sem engordar a barra.
                             */
                            className="gap-2.5"
                        >
                            <Link href={item.href} prefetch>
                                <item.icon />
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
