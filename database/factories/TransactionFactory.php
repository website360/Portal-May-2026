<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $dueDate = fake()->dateTimeBetween('-3 months', '+3 months');

        return [
            'type' => fake()->randomElement(Transaction::TYPES),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 80, 9_000),
            'due_date' => $dueDate,
            'paid_at' => null,
            'paid_amount' => null,
            'cost_center_id' => CostCenter::factory(),
            'finance_category_id' => null,
            'client_id' => null,
            'counterpart' => fake()->company(),
            'payment_method' => fake()->randomElement(['Pix', 'Boleto', 'Cartão', 'Transferência', 'Débito automático']),
            'notes' => null,
        ];
    }

    public function payable(): static
    {
        return $this->state(fn () => ['type' => Transaction::TYPE_PAYABLE]);
    }

    public function receivable(): static
    {
        return $this->state(fn () => ['type' => Transaction::TYPE_RECEIVABLE]);
    }

    public function dueIn(int $days): static
    {
        return $this->state(fn () => ['due_date' => now()->addDays($days)]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(random_int(1, 45)),
            'paid_at' => null,
            'paid_amount' => null,
        ]);
    }

    /**
     * Baixa sem valor próprio: `paid_amount` fica nulo e vale o previsto. Copiar
     * o `amount` aqui não funcionaria — atributos passados no `create()` chegam
     * depois dos states, então o state veria só o valor aleatório do padrão.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_at' => $attributes['due_date'] ?? now(),
            'paid_amount' => null,
        ]);
    }
}
