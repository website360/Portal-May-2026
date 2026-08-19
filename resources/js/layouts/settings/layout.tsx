import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, Plug, Sparkles, CreditCard, KeyRound, Mail, MessageCircle, MessageSquareText, Palette, ScrollText, Tag, Tags, Truck, UserRound, Users, type LucideIcon } from 'lucide-react';

interface SettingsLink {
    title: string;
    href: string;
    icon: LucideIcon;
}

/**
 * Duas naturezas no mesmo lugar: o que é da conta de quem usa e o que é do
 * sistema. Separadas por grupo para não virar uma lista sem hierarquia.
 */
const groups: { label: string; items: SettingsLink[]; adminOnly?: boolean }[] = [
    {
        label: 'Sua conta',
        items: [
            { title: 'Perfil', href: '/configuracoes/perfil', icon: UserRound },
            { title: 'Senha', href: '/configuracoes/senha', icon: KeyRound },
            { title: 'Aparência', href: '/configuracoes/aparencia', icon: Palette },
        ],
    },
    {
        label: 'Sistema',
        adminOnly: true,
        items: [
            { title: 'Usuários', href: '/configuracoes/usuarios', icon: Users },
            { title: 'WhatsApp', href: '/configuracoes/whatsapp', icon: MessageCircle },
            { title: 'E-mail', href: '/configuracoes/email', icon: Mail },
            { title: 'Marca', href: '/configuracoes/marca', icon: Sparkles },
            { title: 'Asaas', href: '/configuracoes/asaas', icon: Plug },
        ],
    },
    {
        label: 'Conteúdo',
        items: [
            { title: 'Mensagens', href: '/configuracoes/mensagens', icon: MessageSquareText },
            { title: 'Modelos de contrato', href: '/configuracoes/modelos-de-contrato', icon: ScrollText },
        ],
    },
    {
        label: 'Financeiro',
        items: [
            { title: 'Centros de custo', href: '/configuracoes/financeiro/centros-de-custo', icon: Building2 },
            { title: 'Fornecedores', href: '/configuracoes/financeiro/fornecedores', icon: Truck },
            { title: 'Categorias', href: '/configuracoes/financeiro/categorias', icon: Tags },
            { title: 'Etiquetas', href: '/configuracoes/financeiro/etiquetas', icon: Tag },
            { title: 'Formas de pagamento', href: '/configuracoes/financeiro/formas-de-pagamento', icon: CreditCard },
        ],
    },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = window.location.pathname;
    const { auth } = usePage<SharedData>().props;

    // Esconder é cortesia; quem barra de verdade é o servidor.
    const visible = groups.filter((group) => !group.adminOnly || auth.isAdmin);

    return (
        <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
            <div className="space-y-1">
                <h1 className="text-2xl font-bold tracking-tight">Configurações</h1>
                <p className="text-muted-foreground text-sm">Sua conta e os cadastros que o sistema usa.</p>
            </div>

            <div className="flex min-w-0 flex-col gap-8 lg:flex-row lg:gap-10">
                <aside className="w-full shrink-0 lg:w-56">
                    <nav className="flex flex-col gap-6">
                        {visible.map((group) => (
                            <div key={group.label} className="space-y-1">
                                <p className="text-muted-foreground px-3 text-xs font-medium">{group.label}</p>

                                {group.items.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        prefetch
                                        className={cn(
                                            'flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors',
                                            'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden',
                                            currentPath === item.href
                                                ? 'bg-accent text-accent-foreground font-medium'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                                        )}
                                    >
                                        <item.icon className="size-4 shrink-0" />
                                        {item.title}
                                    </Link>
                                ))}
                            </div>
                        ))}
                    </nav>
                </aside>

                <Separator className="lg:hidden" />

                {/* Full width: o conteúdo ocupa o que sobra, sem teto artificial. */}
                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}
