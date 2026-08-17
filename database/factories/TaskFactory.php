<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /** Recados de agência mesmo — o que aparece numa lista de dia a dia. */
    private const TITLES = [
        'Aprovar layout com o cliente',
        'Revisar copy da campanha',
        'Subir criativos no gerenciador',
        'Fechar escopo da proposta',
        'Enviar relatório mensal',
        'Ajustar responsivo do site',
        'Agendar reunião de alinhamento',
        'Emitir nota fiscal',
        'Renovar hospedagem',
        'Pedir depoimento para o case',
        'Trocar o banner da home',
        'Conferir métricas da semana',
        'Responder o e-mail do jurídico',
        'Fazer backup do site',
    ];

    public function definition(): array
    {
        $status = fake()->randomElement([
            Task::STATUS_PENDING,
            Task::STATUS_PENDING,
            Task::STATUS_DOING,
            Task::STATUS_DONE,
        ]);

        return [
            'project_id' => null,
            'client_id' => null,
            'user_id' => null,
            'title' => fake()->randomElement(self::TITLES),
            'description' => fake()->boolean(25) ? fake()->sentence(10) : null,
            'status' => $status,
            'priority' => fake()->randomElement([
                Task::PRIORITY_LOW,
                Task::PRIORITY_NORMAL,
                Task::PRIORITY_NORMAL,
                Task::PRIORITY_HIGH,
                Task::PRIORITY_URGENT,
            ]),
            'due_date' => fake()->boolean(75) ? fake()->dateTimeBetween('-2 weeks', '+4 weeks') : null,
            'completed_at' => $status === Task::STATUS_DONE ? fake()->dateTimeBetween('-2 weeks', 'now') : null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => Task::STATUS_PENDING, 'completed_at' => null]);
    }

    public function doing(): static
    {
        return $this->state(fn () => ['status' => Task::STATUS_DOING, 'completed_at' => null]);
    }

    public function done(): static
    {
        return $this->state(fn () => ['status' => Task::STATUS_DONE, 'completed_at' => now()]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => Task::STATUS_PENDING,
            'completed_at' => null,
            'due_date' => now()->subDays(random_int(1, 20)),
        ]);
    }

    public function dueIn(int $days): static
    {
        return $this->state(fn () => ['due_date' => now()->addDays($days)]);
    }
}
