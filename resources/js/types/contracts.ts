import type { ClientOption } from '@/types/domains';

export type ContractStatus = 'draft' | 'active' | 'expiring' | 'ended' | 'cancelled';

export type BillingPeriod = 'monthly' | 'annual';

/** Uma renovação registrada: o antes/depois de vigência e valor. */
export interface ContractRenewal {
    renewed_at: string;
    from_ends_at: string | null;
    to_ends_at: string;
    from_value: number | null;
    to_value: number | null;
}

export const billingPeriodLabels: Record<BillingPeriod, string> = {
    monthly: 'Mensal',
    annual: 'Anual',
};

/** Um marcador que o modelo pede e o sistema não sabe preencher sozinho. */
export interface TemplateField {
    key: string;
    label: string;
}

export interface ContractTemplate {
    id: number;
    name: string;
    description: string | null;
    body: string;
    active: boolean;
    /** Contrato impresso leva linha de assinatura; o que vai para a Clicksign, nao. */
    with_signatures: boolean;
    contracts_count: number;
    fields: TemplateField[];
}

/** O que a tela de geração recebe: sem contagem, com o corpo para a prévia. */
export interface GeneratorTemplate {
    id: number;
    name: string;
    description: string | null;
    body: string;
    fields: TemplateField[];
}

export interface Contract {
    id: number;
    client_id: number;
    contract_template_id: number | null;
    number: string;
    title: string;
    service: string;
    value: number | null;
    starts_at: string;
    starts_label: string;
    ends_at: string | null;
    ends_label: string | null;
    /** Negativo quando a vigência já acabou; null em prazo indeterminado. */
    days_left: number | null;
    status: ContractStatus;
    billing_period: BillingPeriod | null;
    price_review_at: string | null;
    price_review_label: string | null;
    price_review_years: number | null;
    /** Dias até o próximo reajuste; negativo quando já passou; null sem reajuste marcado. */
    review_days: number | null;
    review_due: boolean;
    renewals: ContractRenewal[];
    signed_at: string | null;
    signed_label: string | null;
    cancelled: boolean;
    has_attachment: boolean;
    has_body: boolean;
    body: string | null;
    variables: Record<string, string> | null;
    notes: string | null;
    client: { id: number; name: string; photo_url: string | null };
}

export interface ContractStats {
    total: number;
    active: number;
    expiring: number;
    draft: number;
    review: number;
}

export interface ContractFilters {
    search: string;
    statuses: string[];
    clients: string[];
    services: string[];
    sort: string;
    direction: 'asc' | 'desc';
}

export const CONTRACT_FILTER_KEYS = ['statuses', 'clients', 'services'] as const;

export const statusOptions = [
    { value: 'draft', label: 'Aguardando assinatura' },
    { value: 'active', label: 'Vigente' },
    { value: 'expiring', label: 'Vence em 30 dias' },
    { value: 'ended', label: 'Encerrado' },
    { value: 'cancelled', label: 'Cancelado' },
];

/** Agrupamento dos marcadores prontos, como o servidor manda. */
export type PlaceholderCatalog = Record<string, Record<string, string>>;

/**
 * O que o servidor encontrou de arte e tipografia para o PDF.
 *
 * Existe porque isto falhava calado: sem retorno na tela, descobrir que o
 * arquivo não foi lido exigia gerar um contrato e reparar no fundo branco.
 */
export interface ContractArt {
    letterhead: { found: boolean; path: string };
    logo: { found: boolean; path: string };
    font: { found: boolean; name: string; folder: string; missing: string[] };
    /** Extensões de imagem aceitas, para a tela poder dizer quais servem. */
    extensions: Record<string, string>;
    /** Pastas em public/ que escondem a rota de mesmo nome e derrubam a página. */
    shadowing: string[];
}

export type { ClientOption };
