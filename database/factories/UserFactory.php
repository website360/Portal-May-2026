<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            /*
             * Administrador por padrão. Os testes de cada módulo exercitam o
             * módulo, não a permissão — se o padrão fosse "sem acesso", todos
             * eles precisariam pedir acesso antes de testar qualquer coisa, e o
             * ruído esconderia o que cada um de fato verifica.
             *
             * Quem testa permissão cria `member()` de propósito, e é lá que a
             * negativa é o assunto.
             */
            'role' => User::ROLE_ADMIN,
            'permissions' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN, 'permissions' => null]);
    }

    /**
     * @param  array<string, string>  $permissions
     */
    public function member(array $permissions = []): static
    {
        return $this->state(fn () => [
            'role' => User::ROLE_MEMBER,
            'permissions' => Permissions::sanitize($permissions),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
