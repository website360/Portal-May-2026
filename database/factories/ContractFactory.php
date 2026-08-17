<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'number' => fn () => Contract::nextNumber(),
            'title' => 'Prestação de serviços',
            'service' => 'Hospedagem + Manutenção',
            'value' => 500,
            'starts_at' => now()->subMonths(2)->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'body' => 'Texto do contrato.',
            'signed_at' => now()->subMonths(2)->toDateString(),
        ];
    }

    /** Ainda sem assinatura. */
    public function draft(): static
    {
        return $this->state(['signed_at' => null]);
    }

    public function endingIn(int $days): static
    {
        return $this->state(['ends_at' => now()->addDays($days)->toDateString()]);
    }
}
