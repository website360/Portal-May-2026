export type TicketStatus = 'open' | 'doing' | 'resolved' | 'closed';

export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';

export const ticketStatusOrder: TicketStatus[] = ['open', 'doing', 'resolved', 'closed'];

export const ticketStatusLabels: Record<TicketStatus, string> = {
    open: 'Aberto',
    doing: 'Em andamento',
    resolved: 'Resolvido',
    closed: 'Fechado',
};

export const ticketPriorityOrder: TicketPriority[] = ['low', 'normal', 'high', 'urgent'];

export const ticketPriorityLabels: Record<TicketPriority, string> = {
    low: 'Baixa',
    normal: 'Normal',
    high: 'Alta',
    urgent: 'Urgente',
};

export const ticketChannelLabels: Record<string, string> = {
    manual: 'Manual',
    whatsapp: 'WhatsApp',
    email: 'E-mail',
};

export interface TicketListItem {
    id: number;
    number: string;
    subject: string;
    status: TicketStatus;
    priority: TicketPriority;
    category: string | null;
    channel: string;
    messages_count: number;
    last_reply_label: string | null;
    created_label: string;
    client: { id: number; name: string; photo_url: string | null } | null;
    assignee: { id: number; name: string } | null;
}

export interface TicketAttachment {
    id: number;
    name: string;
    url: string;
    size: number;
}

export interface TicketMessage {
    id: number;
    body: string;
    internal: boolean;
    user: { id: number; name: string } | null;
    created_label: string;
    created_at: string;
    attachments: TicketAttachment[];
}

export interface TicketDetail extends TicketListItem {
    opener: { id: number; name: string } | null;
    created_at_label: string;
    client_id: number | null;
    assignee_id: number | null;
    messages: TicketMessage[];
}

export interface TicketStats {
    open: number;
    unassigned: number;
    urgent: number;
    resolved: number;
}

export interface TicketFilters {
    search: string;
    status: string;
    priority: string;
    assignee: string;
    client: string;
}

export interface AgentOption {
    value: string;
    label: string;
}
