import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { activeNavHref } from '@/config/navigation';
import { Link, usePage } from '@inertiajs/react';
import { type LucideIcon } from 'lucide-react';

type NavItem = { title: string; href: string; icon: LucideIcon };

export function NavMain({ items, label }: { items: NavItem[]; label?: string }) {
    const page = usePage();
    const activeHref = activeNavHref(items, page.url);

    return (
        <SidebarGroup className="px-2 py-1">
            {label && <SidebarGroupLabel className="mb-1 text-[0.7rem] font-semibold tracking-wider uppercase">{label}</SidebarGroupLabel>}
            <SidebarMenu className="gap-1.5">
                {items.map((item) => (
                    <SidebarMenuItem key={item.href}>
                        <SidebarMenuButton
                            asChild
                            isActive={item.href === activeHref}
                            tooltip={item.title}
                            className="h-10 gap-3 text-[0.9375rem] [&>svg]:size-5"
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
