export type DomainManagedBy = 'agency' | 'client';

export type DomainStatus = 'expired' | 'expiring' | 'ok' | 'unknown';

export interface Domain {
    id: number;
    client_id: number;
    name: string;
    registrar: string | null;
    managed_by: DomainManagedBy;
    registered_at: string | null;
    expires_at: string | null;
    expires_at_label: string | null;
    auto_renew: boolean;
    annual_cost: number | null;
    notes: string | null;
    /** Calculada no servidor a partir do vencimento. */
    status: DomainStatus;
    /** Negativo quando já venceu; null quando não há data. */
    days_left: number | null;
}

export interface DomainWithClient extends Domain {
    client: { id: number; name: string; photo_url: string | null };
}

export interface DomainStats {
    total: number;
    agency: number;
    client: number;
    expiring: number;
    expired: number;
}

export interface DomainFilters {
    search: string;
    managed_by: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
}

export interface ClientOption {
    id: number;
    name: string;
    /** Termos que encontram o cliente sem aparecer na linha — razão social, documento. */
    search?: string;
}

export interface DomainFormData {
    client_id: string;
    name: string;
    registrar: string;
    managed_by: DomainManagedBy;
    registered_at: string;
    expires_at: string;
    auto_renew: boolean;
    annual_cost: string;
    notes: string;

    [key: string]: string | boolean;
}

export const EMPTY_DOMAIN_FORM: DomainFormData = {
    client_id: '',
    name: '',
    registrar: '',
    managed_by: 'agency',
    registered_at: '',
    expires_at: '',
    auto_renew: false,
    annual_cost: '',
    notes: '',
};

export function toDomainFormData(domain: Domain): DomainFormData {
    return {
        client_id: String(domain.client_id),
        name: domain.name,
        registrar: domain.registrar ?? '',
        managed_by: domain.managed_by,
        registered_at: domain.registered_at ?? '',
        expires_at: domain.expires_at ?? '',
        auto_renew: domain.auto_renew,
        annual_cost: domain.annual_cost === null ? '' : String(domain.annual_cost),
        notes: domain.notes ?? '',
    };
}
