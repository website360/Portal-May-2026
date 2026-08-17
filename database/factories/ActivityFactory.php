<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    private const DESCRIPTIONS = [
        'cadastrou um novo cliente',
        'aprovou a proposta comercial',
        'concluiu uma tarefa do projeto',
        'emitiu uma fatura',
        'atualizou o status de um projeto',
        'registrou o pagamento de uma fatura',
        'adicionou um comentário no briefing',
    ];

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'description' => fake()->randomElement(self::DESCRIPTIONS),
            'subject_type' => null,
            'subject_id' => null,
        ];
    }
}
