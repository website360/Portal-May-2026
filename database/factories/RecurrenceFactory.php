<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\Recurrence;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurrence>
 */
class RecurrenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => Transaction::TYPE_PAYABLE,
            'description' => fake()->randomElement(['Hospedagem anual', 'Licença do Adobe', 'Renovação de domínio', 'Contrato de manutenção']),
            'amount' => fake()->randomFloat(2, 90, 4800),
            'interval' => Recurrence::ANNUAL,
            'next_due_at' => now()->addMonths(2)->toDateString(),
            'ends_at' => null,
            'active' => true,
            'cost_center_id' => CostCenter::factory(),
        ];
    }

    public function annual(): static
    {
        return $this->state(fn () => ['interval' => Recurrence::ANNUAL]);
    }

    public function monthly(): static
    {
        return $this->state(fn () => ['interval' => Recurrence::MONTHLY]);
    }

    public function receivable(): static
    {
        return $this->state(fn () => ['type' => Transaction::TYPE_RECEIVABLE]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    /** Vencendo daqui a N dias (negativo = já venceu). */
    public function dueIn(int $days): static
    {
        return $this->state(fn () => ['next_due_at' => now()->addDays($days)->toDateString()]);
    }
}
