<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;

class ContractRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'contract_template_id' => ['nullable', 'exists:contract_templates,id'],
            'title' => ['required', 'string', 'max:180'],
            'service' => ['required', 'string', 'max:120'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'signed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'billing_period' => ['nullable', 'in:monthly,annual'],
            'price_review_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'price_review_years' => ['nullable', 'integer', 'min:1', 'max:20'],

            // Os marcadores livres do modelo, preenchidos na tela.
            'variables' => ['array'],
            'variables.*' => ['nullable', 'string', 'max:1000'],

            // O PDF assinado, quando existe. 20 MB cobre contrato digitalizado.
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'contract_template_id' => 'modelo',
            'title' => 'título',
            'service' => 'serviço',
            'value' => 'valor',
            'starts_at' => 'início da vigência',
            'ends_at' => 'fim da vigência',
            'signed_at' => 'data da assinatura',
            'billing_period' => 'período contratado',
            'price_review_at' => 'próximo reajuste',
            'price_review_years' => 'período de reajuste',
            'pdf' => 'arquivo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after_or_equal' => 'O fim da vigência não pode ser antes do início.',
            'pdf.mimes' => 'O arquivo precisa ser um PDF.',
            'pdf.max' => 'O PDF pode ter no máximo 20 MB.',
        ];
    }
}
