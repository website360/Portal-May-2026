<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'tarefa',
            'description' => 'detalhes',
            'status' => 'situação',
            'priority' => 'prioridade',
            'due_date' => 'prazo',
            'client_id' => 'cliente',
            'project_id' => 'projeto',
            'user_id' => 'responsável',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            collect($this->all())
                ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
                ->all()
        );

        // Defaults do cadastro rápido, que envia só o título.
        $this->mergeIfMissing([
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_NORMAL,
        ]);
    }
}
