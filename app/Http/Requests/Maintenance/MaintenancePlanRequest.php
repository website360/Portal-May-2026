<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenancePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $plan = $this->route('plano');

        return [
            'client_id' => ['required', 'exists:clients,id'],
            'site_url' => [
                'required', 'string', 'max:255',
                Rule::unique('maintenance_plans')
                    ->where(fn ($query) => $query->where('client_id', $this->input('client_id')))
                    ->ignore($plan),
            ],
            'active' => ['boolean'],
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
            'site_url' => 'site',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_url.unique' => 'Esse cliente já tem um plano para este site.',
        ];
    }

    /** Aceita o endereço como a pessoa escreve: com http, com barra no fim, com espaço. */
    protected function prepareForValidation(): void
    {
        $site = trim($this->input('site_url', ''));
        $site = preg_replace('#^https?://#i', '', $site) ?? $site;

        $this->merge(['site_url' => rtrim($site, '/')]);
    }
}
