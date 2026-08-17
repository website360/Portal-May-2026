<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras compartilhadas por criacao e edicao. As chaves seguem as quatro etapas
 * do formulario; o front usa os nomes dos campos para saber em qual etapa
 * mostrar cada erro que volta do servidor.
 */
class ClientRequest extends FormRequest
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
        $client = $this->route('cliente');

        return [
            // Etapa 1 — identificacao
            'type' => ['required', Rule::in([Client::TYPE_COMPANY, Client::TYPE_PERSON])],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'document' => [
                'nullable', 'string', 'max:20',
                Rule::unique('clients', 'document')->ignore($client),
            ],
            'photo' => ['nullable', 'image', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],

            // Etapa 2 — contato
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_role' => ['nullable', 'string', 'max:255'],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'representative_role' => ['nullable', 'string', 'max:255'],
            'representative_document' => ['nullable', 'string', 'max:32'],

            // Etapa 3 — endereco
            'zip_code' => ['nullable', 'string', 'max:9'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],

            // Etapa 4 — comercial
            'status' => ['required', Rule::in([Client::STATUS_ACTIVE, Client::STATUS_INACTIVE])],
            'segment' => ['nullable', 'string', 'max:255'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'started_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'tipo',
            'name' => 'nome',
            'trade_name' => 'nome fantasia',
            'document' => 'documento',
            'photo' => 'foto',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'contact_name' => 'nome do contato',
            'contact_role' => 'cargo do contato',
            'representative_name' => 'representante legal',
            'representative_role' => 'cargo do representante',
            'representative_document' => 'CPF do representante',
            'zip_code' => 'CEP',
            'street' => 'logradouro',
            'number' => 'número',
            'complement' => 'complemento',
            'district' => 'bairro',
            'city' => 'cidade',
            'state' => 'UF',
            'status' => 'situação',
            'segment' => 'segmento',
            'monthly_fee' => 'mensalidade',
            'started_at' => 'cliente desde',
            'notes' => 'observações',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document.unique' => 'Já existe um cliente com este documento.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Strings vazias vindas do formulario viram null, para nao gravar "" no banco.
        $this->merge(
            collect($this->all())
                ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
                ->all()
        );
    }
}
