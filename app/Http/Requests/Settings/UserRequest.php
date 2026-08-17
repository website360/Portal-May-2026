<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('usuario');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],

            // Na criação a senha é obrigatória; na edição, só se for trocar.
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],

            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_MEMBER])],

            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::LEVELS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'password' => 'senha',
            'role' => 'perfil de acesso',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Já existe um usuário com esse e-mail.',
        ];
    }
}
