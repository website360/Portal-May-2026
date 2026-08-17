export type ClientType = 'company' | 'person';

export type ClientStatus = 'active' | 'inactive';

export interface Client {
    id: number;
    type: ClientType;
    name: string;
    trade_name: string | null;
    document: string | null;
    photo_url: string | null;
    status: ClientStatus;

    email: string | null;
    phone: string | null;
    contact_name: string | null;
    contact_role: string | null;
    representative_name: string | null;
    representative_role: string | null;
    representative_document: string | null;

    zip_code: string | null;
    street: string | null;
    number: string | null;
    complement: string | null;
    district: string | null;
    city: string | null;
    state: string | null;

    segment: string | null;
    monthly_fee: number | null;
    started_at: string | null;
    notes: string | null;

    projects_count: number;
}

export interface ClientStats {
    total: number;
    active: number;
    inactive: number;
    company: number;
    person: number;
}

export interface ClientFilters {
    search: string;
    status: string;
    type: string;
    sort: string;
    direction: 'asc' | 'desc';
}

/**
 * O formulario trabalha com strings — e o que os inputs devolvem — mais o
 * arquivo da foto. A conversao para numero e data acontece no servidor.
 *
 * `photo` guarda o arquivo novo escolhido agora; `remove_photo` sinaliza que a
 * foto ja gravada deve ser apagada. Os dois sao entradas do formulario, nao
 * colunas do cliente.
 */
export interface ClientFormData {
    type: ClientType;
    name: string;
    trade_name: string;
    document: string;
    photo: File | null;
    remove_photo: string;
    status: ClientStatus;

    email: string;
    phone: string;
    contact_name: string;
    contact_role: string;
    representative_name: string;
    representative_role: string;
    representative_document: string;

    zip_code: string;
    street: string;
    number: string;
    complement: string;
    district: string;
    city: string;
    state: string;

    segment: string;
    monthly_fee: string;
    started_at: string;
    notes: string;

    [key: string]: string | File | null;
}

export const EMPTY_CLIENT_FORM: ClientFormData = {
    type: 'company',
    name: '',
    trade_name: '',
    document: '',
    photo: null,
    remove_photo: '',
    status: 'active',

    email: '',
    phone: '',
    contact_name: '',
    contact_role: '',
    representative_name: '',
    representative_role: '',
    representative_document: '',

    zip_code: '',
    street: '',
    number: '',
    complement: '',
    district: '',
    city: '',
    state: '',

    segment: '',
    monthly_fee: '',
    started_at: '',
    notes: '',
};

export function toFormData(client: Client): ClientFormData {
    const text = (value: string | null) => value ?? '';

    return {
        type: client.type,
        name: client.name,
        trade_name: text(client.trade_name),
        document: text(client.document),
        photo: null,
        remove_photo: '',
        status: client.status,

        email: text(client.email),
        phone: text(client.phone),
        contact_name: text(client.contact_name),
        contact_role: text(client.contact_role),
        representative_name: text(client.representative_name),
        representative_role: text(client.representative_role),
        representative_document: text(client.representative_document),

        zip_code: text(client.zip_code),
        street: text(client.street),
        number: text(client.number),
        complement: text(client.complement),
        district: text(client.district),
        city: text(client.city),
        state: text(client.state),

        segment: text(client.segment),
        monthly_fee: client.monthly_fee === null ? '' : String(client.monthly_fee),
        started_at: text(client.started_at),
        notes: text(client.notes),
    };
}
