export type RepeatMode = 'once' | 'installments' | 'recurring';

export type TransactionType = 'payable' | 'receivable';

export type TransactionStatus = 'pending' | 'overdue' | 'paid';

export type CategoryType = 'income' | 'expense';

interface Tagged {
    id: number;
    name: string;
    color: string;
}

export interface CostCenter extends Tagged {
    description?: string | null;
    active?: boolean;
    transactions_count?: number;
}

/** Por onde o dinheiro entra ou sai: Pix, boleto, cartão. */
export interface PaymentMethod extends Tagged {
    description?: string | null;
    active?: boolean;
    transactions_count?: number;
}

export interface FinanceCategory extends Tagged {
    type: CategoryType;
    active?: boolean;
    transactions_count?: number;
}

export interface Transaction {
    id: number;
    type: TransactionType;
    description: string;
    amount: number;
    due_date: string | null;
    due_date_label: string | null;
    paid_at: string | null;
    paid_at_label: string | null;
    paid_amount: number | null;
    /** Derivada no servidor a partir do vencimento e da baixa. */
    status: TransactionStatus;
    days_left: number;

    cost_center_id: number | null;
    finance_category_id: number | null;
    client_id: number | null;

    cost_center: Tagged | null;
    category: Tagged | null;
    client: { id: number; name: string } | null;

    counterpart: string | null;
    supplier_id: number | null;
    payment_method: string | null;
    payment_method_id: number | null;
    notes: string | null;
    installment: number | null;
    installments: number | null;
    /** Como a conta nasceu: avulsa, parcela, ou cobranca de contrato. */
    kind: 'once' | 'installments' | 'recurring';
    series_id: string | null;
}

interface SummaryCard {
    amount: number;
    count: number;
}

export interface FinanceSummary {
    /** Vazio quando não há filtro de período. */
    month: string;
    payable: { total: SummaryCard; paid: SummaryCard; overdue: SummaryCard };
    receivable: { total: SummaryCard; paid: SummaryCard; open: SummaryCard };
}

export interface FinanceFilters {
    search: string;
    /** Listas: nada marcado significa "todos". */
    type: string[];
    status: string[];
    cost_center_id: string[];
    finance_category_id: string[];
    month: string;
    sort: string;
    direction: 'asc' | 'desc';
}

export interface TransactionFormData {
    type: TransactionType;
    description: string;
    amount: string;
    due_date: string;
    cost_center_id: string;
    finance_category_id: string;
    client_id: string;
    supplier_id: string;
    counterpart: string;
    payment_method_id: string;
    notes: string;
    paid_at: string;
    paid_amount: string;
    installments: string;
    repeat: RepeatMode;
    interval: string;
    occurrences: string;

    [key: string]: string;
}

export const EMPTY_TRANSACTION_FORM: TransactionFormData = {
    type: 'payable',
    description: '',
    amount: '',
    due_date: '',
    cost_center_id: '',
    finance_category_id: '',
    client_id: '',
    supplier_id: '',
    counterpart: '',
    payment_method_id: '',
    notes: '',
    paid_at: '',
    paid_amount: '',
    installments: '2',
    repeat: 'once',
    interval: 'monthly',
    occurrences: '',
};

export function toTransactionFormData(transaction: Transaction): TransactionFormData {
    const text = (value: string | null) => value ?? '';
    const id = (value: number | null) => (value === null ? '' : String(value));

    return {
        type: transaction.type,
        description: transaction.description,
        amount: String(transaction.amount),
        due_date: text(transaction.due_date),
        cost_center_id: id(transaction.cost_center_id),
        finance_category_id: id(transaction.finance_category_id),
        client_id: id(transaction.client_id),
        supplier_id: id(transaction.supplier_id),
        counterpart: text(transaction.counterpart),
        payment_method_id: id(transaction.payment_method_id),
        notes: text(transaction.notes),
        paid_at: text(transaction.paid_at),
        paid_amount: transaction.paid_amount === null ? '' : String(transaction.paid_amount),
        installments: '2',
        repeat: 'once',
        interval: 'monthly',
        occurrences: '',
    };
}

/** Categoria de receita serve lançamento a receber; de despesa, a pagar. */
export function categoriesForType(categories: FinanceCategory[], type: TransactionType): FinanceCategory[] {
    const wanted: CategoryType = type === 'receivable' ? 'income' : 'expense';

    return categories.filter((category) => category.type === wanted);
}
