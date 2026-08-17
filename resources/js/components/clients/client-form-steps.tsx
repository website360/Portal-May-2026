import { ClientAvatar } from '@/components/clients/client-avatar';
import { Combobox } from '@/components/ui/combobox';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhotoPicker } from '@/components/ui/photo-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { maskCpf, maskDocument, maskPhone, maskZipCode } from '@/lib/masks';
import { cn } from '@/lib/utils';
import type { ClientFormData } from '@/types/clients';
import { Building2, Handshake, MapPin, Phone, type LucideIcon } from 'lucide-react';

/**
 * As quatro etapas do cadastro. `fields` serve para dois usos: barrar o avanco
 * quando falta um obrigatorio, e descobrir em qual etapa mostrar cada erro que
 * volta do servidor.
 */
export interface ClientStep {
    id: string;
    label: string;
    description: string;
    icon: LucideIcon;
    fields: (keyof ClientFormData)[];
    required: (keyof ClientFormData)[];
}

export const CLIENT_STEPS: ClientStep[] = [
    {
        id: 'identificacao',
        label: 'Identificação',
        description: 'Quem é o cliente',
        icon: Building2,
        fields: ['type', 'name', 'trade_name', 'document', 'photo'],
        required: ['name'],
    },
    {
        id: 'contato',
        label: 'Contato',
        description: 'Como falar com ele',
        icon: Phone,
        fields: ['email', 'phone', 'contact_name', 'contact_role', 'representative_name', 'representative_role', 'representative_document'],
        required: [],
    },
    {
        id: 'endereco',
        label: 'Endereço',
        description: 'Onde ele fica',
        icon: MapPin,
        fields: ['zip_code', 'street', 'number', 'complement', 'district', 'city', 'state'],
        required: [],
    },
    {
        id: 'comercial',
        label: 'Comercial',
        description: 'Situação e valores',
        icon: Handshake,
        fields: ['status', 'segment', 'monthly_fee', 'started_at', 'notes'],
        required: [],
    },
];

const UFS = [
    'AC',
    'AL',
    'AP',
    'AM',
    'BA',
    'CE',
    'DF',
    'ES',
    'GO',
    'MA',
    'MT',
    'MS',
    'MG',
    'PA',
    'PB',
    'PR',
    'PE',
    'PI',
    'RJ',
    'RN',
    'RS',
    'RO',
    'RR',
    'SC',
    'SP',
    'SE',
    'TO',
];

type Errors = Partial<Record<string, string>>;

interface StepFieldsProps {
    stepIndex: number;
    data: ClientFormData;
    setData: (field: keyof ClientFormData, value: string | File | null) => void;
    errors: Errors;
    /** Foto já gravada, em edição. Nula em cadastro. */
    existingPhotoUrl: string | null;
}

export function ClientStepFields({ stepIndex, data, setData, errors, existingPhotoUrl }: StepFieldsProps) {
    const props = { data, setData, errors, existingPhotoUrl };

    if (stepIndex === 0) return <IdentificationFields {...props} />;
    if (stepIndex === 1) return <ContactFields {...props} />;
    if (stepIndex === 2) return <AddressFields {...props} />;

    return <CommercialFields {...props} />;
}

type FieldsProps = Omit<StepFieldsProps, 'stepIndex'>;

function IdentificationFields({ data, setData, errors, existingPhotoUrl }: FieldsProps) {
    const isCompany = data.type === 'company';

    return (
        <div className="grid gap-5">
            <Field label="Foto" error={errors.photo}>
                <PhotoPicker
                    renderAvatar={(url) => <ClientAvatar name={data.name || '?'} photoUrl={url} className="size-16 text-base" />}
                    file={data.photo}
                    existingUrl={existingPhotoUrl}
                    removed={data.remove_photo === '1'}
                    onSelect={(file) => {
                        setData('photo', file);
                        setData('remove_photo', '');
                    }}
                    onRemove={() => {
                        // Descarta primeiro o arquivo recém-escolhido; só depois
                        // marca a foto já gravada para exclusão.
                        if (data.photo) {
                            setData('photo', null);
                        } else {
                            setData('remove_photo', '1');
                        }
                    }}
                />
            </Field>

            <Field label="Tipo de cliente" error={errors.type}>
                <div className="grid grid-cols-2 gap-2">
                    <TypeOption
                        active={isCompany}
                        label="Pessoa jurídica"
                        hint="CNPJ"
                        onClick={() => {
                            setData('type', 'company');
                            setData('document', '');
                        }}
                    />
                    <TypeOption
                        active={!isCompany}
                        label="Pessoa física"
                        hint="CPF"
                        onClick={() => {
                            setData('type', 'person');
                            setData('document', '');
                        }}
                    />
                </div>
            </Field>

            <Field label={isCompany ? 'Razão social' : 'Nome completo'} required error={errors.name}>
                <Input
                    id="name"
                    autoFocus
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder={isCompany ? 'May Comunicação Ltda.' : 'Maria Souza'}
                />
            </Field>

            {/* Autônomo também atende por uma marca — "Casa Mui", "By Artees". */}
            <Field label="Nome fantasia" error={errors.trade_name}>
                <Input
                    id="trade_name"
                    value={data.trade_name}
                    onChange={(e) => setData('trade_name', e.target.value)}
                    placeholder={isCompany ? 'Agência May' : 'Casa Mui'}
                />
            </Field>

            <Field label={isCompany ? 'CNPJ' : 'CPF'} error={errors.document}>
                <Input
                    id="document"
                    inputMode="numeric"
                    value={data.document}
                    onChange={(e) => setData('document', maskDocument(e.target.value, data.type))}
                    placeholder={isCompany ? '00.000.000/0000-00' : '000.000.000-00'}
                />
            </Field>
        </div>
    );
}

function ContactFields({ data, setData, errors }: FieldsProps) {
    return (
        <div className="grid gap-5">
            <Field label="E-mail" error={errors.email}>
                <Input
                    id="email"
                    type="email"
                    autoFocus
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="contato@cliente.com.br"
                />
            </Field>

            <Field label="Telefone" error={errors.phone}>
                <Input
                    id="phone"
                    inputMode="tel"
                    value={data.phone}
                    onChange={(e) => setData('phone', maskPhone(e.target.value))}
                    placeholder="(11) 99999-9999"
                />
            </Field>

            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Nome do contato" error={errors.contact_name}>
                    <Input
                        id="contact_name"
                        value={data.contact_name}
                        onChange={(e) => setData('contact_name', e.target.value)}
                        placeholder="Quem responde pela conta"
                    />
                </Field>

                <Field label="Cargo" error={errors.contact_role}>
                    <Input
                        id="contact_role"
                        value={data.contact_role}
                        onChange={(e) => setData('contact_role', e.target.value)}
                        placeholder="Diretor de marketing"
                    />
                </Field>
            </div>

            {/*
                Representante legal é outra coisa que o contato: o contato cuida
                do dia a dia, o representante assina. Em contrato, a qualificação
                das partes pede o nome, o cargo e o CPF de quem assina.
            */}
            <div className="space-y-1 border-t pt-5">
                <p className="text-sm font-medium">Representante legal</p>
                <p className="text-muted-foreground text-xs">Quem assina os contratos. Em branco, o contato acima é usado.</p>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Nome" error={errors.representative_name}>
                    <Input
                        id="representative_name"
                        value={data.representative_name}
                        onChange={(e) => setData('representative_name', e.target.value)}
                        placeholder="Quem assina pela empresa"
                    />
                </Field>

                <Field label="Cargo" error={errors.representative_role}>
                    <Input
                        id="representative_role"
                        value={data.representative_role}
                        onChange={(e) => setData('representative_role', e.target.value)}
                        placeholder="Sócio administrador"
                    />
                </Field>
            </div>

            <Field label="CPF do representante" error={errors.representative_document}>
                <Input
                    id="representative_document"
                    className="sm:max-w-56"
                    value={data.representative_document}
                    onChange={(e) => setData('representative_document', maskCpf(e.target.value))}
                    placeholder="000.000.000-00"
                />
            </Field>
        </div>
    );
}

function AddressFields({ data, setData, errors }: FieldsProps) {
    return (
        <div className="grid gap-5">
            <div className="grid gap-5 sm:grid-cols-[10rem_1fr]">
                <Field label="CEP" error={errors.zip_code}>
                    <Input
                        id="zip_code"
                        inputMode="numeric"
                        autoFocus
                        value={data.zip_code}
                        onChange={(e) => setData('zip_code', maskZipCode(e.target.value))}
                        placeholder="00000-000"
                    />
                </Field>

                <Field label="Logradouro" error={errors.street}>
                    <Input id="street" value={data.street} onChange={(e) => setData('street', e.target.value)} placeholder="Av. Paulista" />
                </Field>
            </div>

            <div className="grid gap-5 sm:grid-cols-[8rem_1fr]">
                <Field label="Número" error={errors.number}>
                    <Input id="number" value={data.number} onChange={(e) => setData('number', e.target.value)} placeholder="1000" />
                </Field>

                <Field label="Complemento" error={errors.complement}>
                    <Input id="complement" value={data.complement} onChange={(e) => setData('complement', e.target.value)} placeholder="Sala 42" />
                </Field>
            </div>

            <Field label="Bairro" error={errors.district}>
                <Input id="district" value={data.district} onChange={(e) => setData('district', e.target.value)} placeholder="Bela Vista" />
            </Field>

            <div className="grid gap-5 sm:grid-cols-[1fr_7rem]">
                <Field label="Cidade" error={errors.city}>
                    <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="São Paulo" />
                </Field>

                <Field label="UF" error={errors.state}>
                    <Combobox
                        id="state"
                        value={data.state}
                        onChange={(value) => setData('state', value)}
                        options={UFS.map((uf) => ({ value: uf, label: uf }))}
                        placeholder="UF"
                        searchPlaceholder="UF…"
                        emptyText="UF não encontrada."
                        clearable
                        clearLabel="Nenhuma"
                    />
                </Field>
            </div>
        </div>
    );
}

function CommercialFields({ data, setData, errors }: FieldsProps) {
    return (
        <div className="grid gap-5">
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Situação" required error={errors.status}>
                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                        <SelectTrigger id="status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Ativo</SelectItem>
                            <SelectItem value="inactive">Inativo</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="Segmento" error={errors.segment}>
                    <Input id="segment" value={data.segment} onChange={(e) => setData('segment', e.target.value)} placeholder="Varejo" />
                </Field>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Mensalidade" error={errors.monthly_fee}>
                    <CurrencyInput id="monthly_fee" value={data.monthly_fee} onChange={(value) => setData('monthly_fee', value)} placeholder="0,00" />
                </Field>

                <Field label="Cliente desde" error={errors.started_at}>
                    <Input id="started_at" type="date" value={data.started_at} onChange={(e) => setData('started_at', e.target.value)} />
                </Field>
            </div>

            <Field label="Observações" error={errors.notes}>
                <Textarea
                    id="notes"
                    rows={4}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    placeholder="Histórico, preferências, combinados com o cliente…"
                />
            </Field>
        </div>
    );
}

function Field({ label, required, error, children }: { label: string; required?: boolean; error?: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>
                {label}
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            {children}
            {error && <p className="text-destructive text-xs font-medium">{error}</p>}
        </div>
    );
}

function TypeOption({ active, label, hint, onClick }: { active: boolean; label: string; hint: string; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex flex-col items-start gap-0.5 rounded-lg border px-3 py-2.5 text-left transition-all duration-150',
                'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden active:scale-[0.98]',
                active ? 'border-primary bg-accent text-accent-foreground shadow-xs' : 'border-input bg-background hover:border-primary/30',
            )}
        >
            <span className="text-sm font-medium">{label}</span>
            <span className="text-muted-foreground text-xs">{hint}</span>
        </button>
    );
}
