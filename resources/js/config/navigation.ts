import type { ModuleKey, PermissionLevel } from '@/types';
import { Blocks, FileText, Globe, LayoutGrid, ListTodo, Users, Wallet, Wrench, type LucideIcon } from 'lucide-react';

export interface NavLink {
    title: string;
    href: string;
    icon: LucideIcon;
    /** Modulo que a permissao controla — o item some sem leitura. */
    module: ModuleKey;
}

/**
 * Fonte unica da navegacao lateral.
 *
 * Adicionar um modulo a sidebar e adicionar uma entrada aqui — o componente de
 * layout nunca precisa ser editado. Mantenha os `href` alinhados com os arquivos
 * de routes/modules/.
 *
 * Lista unica, sem titulos de secao: com meia duzia de itens, agrupar custa mais
 * atencao do que economiza.
 */
export const navigation: NavLink[] = [
    { title: 'Dashboard', href: '/dashboard', icon: LayoutGrid, module: 'dashboard' },
    { title: 'Tarefas', href: '/tarefas', icon: ListTodo, module: 'tarefas' },
    { title: 'Clientes', href: '/clientes', icon: Users, module: 'clientes' },
    { title: 'Domínios', href: '/dominios', icon: Globe, module: 'dominios' },
    { title: 'Manutenção', href: '/manutencao', icon: Wrench, module: 'manutencao' },
    { title: 'Contratos', href: '/contratos', icon: FileText, module: 'contratos' },
    { title: 'Financeiro', href: '/financeiro', icon: Wallet, module: 'financeiro' },
    { title: 'Ferramentas', href: '/ferramentas', icon: Blocks, module: 'ferramentas' },
];

/**
 * Itens que a pessoa pode ao menos ler.
 *
 * Esconder e cortesia, nao seguranca: quem barra de verdade e o middleware no
 * servidor. Mostrar um link que responde 403 seria pior do que nao mostrar.
 */
export function visibleNavigation(permissions: Record<ModuleKey, PermissionLevel>): NavLink[] {
    return navigation.filter((item) => permissions?.[item.module] && permissions[item.module] !== 'none');
}

/** Um item esta ativo na rota exata ou em qualquer sub-rota dele. */
export function isActiveRoute(href: string, currentUrl: string): boolean {
    const path = currentUrl.split('?')[0];

    return path === href || path.startsWith(`${href}/`);
}
