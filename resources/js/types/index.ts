import { LucideIcon } from 'lucide-react';

/** Nivel de acesso a um modulo, do menor para o maior. */
export type PermissionLevel = 'none' | 'read' | 'write';

/** Chave de cada modulo controlado por permissao. */
export type ModuleKey = 'dashboard' | 'tarefas' | 'clientes' | 'dominios' | 'manutencao' | 'contratos' | 'financeiro' | 'ferramentas' | 'configuracoes';

export interface Auth {
    user: User;
    /** Mapa ja resolvido: administrador chega com tudo em 'write'. */
    permissions: Record<ModuleKey, PermissionLevel>;
    isAdmin: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

/** Situacao do WhatsApp para o rodape do menu; nula para quem nao e admin. */
export interface WhatsappStatus {
    configured: boolean;
    status: 'connected' | 'connecting' | 'disconnected';
    checked_at: string | null;
    /** Gravado ha tempo demais: a tela confere sozinha, em segundo plano. */
    stale: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: { success: string | null; warning: string | null };
    whatsapp: WhatsappStatus | null;
    [key: string]: unknown;
}

/** Paginacao do Laravel, como chega nas paginas Inertia. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface User {
    id: number;
    name: string;
    email: string;
    /** URL da foto de perfil; null quando o usuario nao subiu nenhuma. */
    avatar: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
