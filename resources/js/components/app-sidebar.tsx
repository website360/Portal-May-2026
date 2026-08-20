import { NavMain } from '@/components/nav-main';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarInput,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { WhatsappStatusIndicator } from '@/components/whatsapp-status';
import { visibleNavigation } from '@/config/navigation';
import { useInitials } from '@/hooks/use-initials';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, Search, Settings } from 'lucide-react';
import { useState } from 'react';
import AppLogo from './app-logo';

/** A seção de sistema — separada dos módulos do dia a dia. */
const systemNav = [{ title: 'Configurações', href: '/configuracoes/perfil', icon: Settings }];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();
    const [query, setQuery] = useState('');

    const term = query.trim().toLowerCase();
    const matches = (title: string) => title.toLowerCase().includes(term);
    const general = visibleNavigation(auth.permissions).filter((item) => matches(item.title));
    const system = systemNav.filter((item) => matches(item.title));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="gap-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                {/* Busca no menu. Some quando a barra está recolhida em ícones. */}
                <div className="relative px-1 group-data-[collapsible=icon]:hidden">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
                    <SidebarInput value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar no menu…" className="pl-9" />
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Geral" items={general} />
                {system.length > 0 && <NavMain label="Sistema" items={system} />}
            </SidebarContent>

            <SidebarFooter className="gap-2">
                <WhatsappStatusIndicator />

                <SidebarMenu>
                    <SidebarMenuItem>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton size="lg" className="data-[state=open]:bg-sidebar-accent">
                                    <Avatar className="size-8 shrink-0 overflow-hidden rounded-full">
                                        {auth.user.avatar && <AvatarImage src={auth.user.avatar} alt={auth.user.name} className="object-cover" />}
                                        <AvatarFallback className="rounded-full text-xs">{getInitials(auth.user.name)}</AvatarFallback>
                                    </Avatar>
                                    <div className="grid flex-1 text-left leading-tight">
                                        <span className="truncate text-sm font-medium">{auth.user.name}</span>
                                        <span className="text-muted-foreground truncate text-xs">{auth.user.email}</span>
                                    </div>
                                    <ChevronsUpDown className="text-muted-foreground ml-auto size-4 shrink-0" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="min-w-56 rounded-lg" side="top" align="end" sideOffset={8}>
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
