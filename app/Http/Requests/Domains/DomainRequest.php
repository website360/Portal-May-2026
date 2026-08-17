<?php

namespace App\Http\Requests\Domains;

use App\Models\Domain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DomainRequest extends FormRequest
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
            'client_id' => ['required', 'exists:clients,id'],
            'name' => [
                'required', 'string', 'max:255',
                // Sem protocolo, sem barra: so o dominio.
                'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i',
                Rule::unique('domains', 'name')->ignore($this->route('dominio')),
            ],
            'registrar' => ['nullable', 'string', 'max:255'],
            'managed_by' => ['required', Rule::in([Domain::MANAGED_BY_AGENCY, Domain::MANAGED_BY_CLIENT])],
            'registered_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:registered_at'],
            'auto_renew' => ['nullable', 'boolean'],
            'annual_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'name' => 'domínio',
            'registrar' => 'registrador',
            'managed_by' => 'gestão',
            'registered_at' => 'data de registro',
            'expires_at' => 'vencimento',
            'auto_renew' => 'renovação automática',
            'annual_cost' => 'custo anual',
            'notes' => 'observações',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Informe apenas o domínio, sem https:// nem barras. Ex.: agenciamay.com.br',
            'name.unique' => 'Este domínio já está cadastrado.',
            'expires_at.after_or_equal' => 'O vencimento não pode ser anterior à data de registro.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->string('name')->toString();

        $this->merge([
            // Tolera colar "https://www.exemplo.com.br/" e guarda "www.exemplo.com.br".
            'name' => strtolower(trim(preg_replace('#^\w+://#', '', $name), " \t\n\r\0\x0B/")),
        ]);

        $this->merge(
            collect($this->all())
                ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
                ->all()
        );
    }
}
