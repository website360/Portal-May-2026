/** Modelos de mensagem do WhatsApp — o catálogo vem do servidor. */

export interface TriggerVariable {
    key: string;
    label: string;
    example: string;
}

export interface TriggerField {
    key: string;
    label: string;
    type: 'text' | 'number' | 'boolean';
}

export interface Trigger {
    label: string;
    module: string;
    description: string;
    variables: TriggerVariable[];
    fields: TriggerField[];
}

export interface Condition {
    field: string;
    operator: string;
    value: string;
}

export interface MessageTemplate {
    id: number;
    trigger: string;
    name: string;
    variations: string[];
    conditions: Condition[];
    priority: number;
    active: boolean;
    /** As regras já escritas em português, prontas para a lista. */
    rules: string[];
}
