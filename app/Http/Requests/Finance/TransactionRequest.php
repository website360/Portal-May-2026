<?php

namespace App\Http\Requests\Finance;

use App\Models\Recurrence;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(Transaction::TYPES)],
            // Opcional: quando falta, o controller monta a descrição a partir
            // da categoria ou do fornecedor, que é o que a pessoa escreveria.
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'due_date' => ['required', 'date'],

            'cost_center_id' => ['required', 'exists:cost_centers,id'],
            'finance_category_id' => ['nullable', 'exists:finance_categories,id'],
            'client_id' => ['nullable', 'exists:clients,id'],

            'counterpart' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'payment_method_id' => ['nullable', 'exists:payment_methods,id'],
            /*
             * A coluna de texto continua aceita para não invalidar o que já foi
             * digitado antes do cadastro existir, mas o formulário só manda o id.
             */
            'payment_method' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'paid_at' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],

            /*
             * Como o lançamento se repete. São coisas diferentes:
             *
             * - 'installments' fatia uma dívida que já existe inteira. As N
             *   contas nascem agora e ninguém renova nada.
             * - 'recurring' é compromisso que se renova. Cria uma recorrência,
             *   materializa só a primeira cobrança, e avisa antes de acabar.
             */
            'repeat' => ['nullable', Rule::in(['once', 'installments', 'recurring'])],
            'installments' => ['nullable', 'integer', 'min:1', 'max:36'],

            'interval' => ['required_if:repeat,recurring', Rule::in(Recurrence::INTERVALS)],

            /*
             * Quantas cobranças o contrato tem, contando a primeira. Vazio =
             * sem encerramento previsto.
             *
             * Contar é mais natural que datar: "12 mensais" é como o contrato
             * foi combinado, enquanto a data final é uma conta que a pessoa
             * teria de fazer de cabeça — e errar por um mês.
             */
            'occurrences' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // A padrão vira "obrigatório quando repeat for recurring" — cita um
            // campo que a pessoa nunca viu e um valor em inglês.
            'interval.required_if' => 'Escolha de quanto em quanto tempo a cobrança se repete.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'description' => 'descrição',
            'amount' => 'valor',
            'due_date' => 'vencimento',
            'cost_center_id' => 'centro de custo',
            'finance_category_id' => 'categoria',
            'client_id' => 'cliente',
            'counterpart' => 'fornecedor ou pagador',
            'payment_method' => 'forma de pagamento',
            'payment_method_id' => 'forma de pagamento',
            'supplier_id' => 'fornecedor',
            'notes' => 'observações',
            'paid_at' => 'data do pagamento',
            'paid_amount' => 'valor pago',
            'installments' => 'parcelas',
            'interval' => 'intervalo',
            'occurrences' => 'quantidade de cobranças',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            collect($this->all())
                ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
                ->all()
        );
    }
}
