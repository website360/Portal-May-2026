<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
{
    protected $model = MaintenancePlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'site_url' => 'www.'.fake()->unique()->domainName(),
            'active' => true,
            'notes' => null,
        ];
    }

    /**
     * Plano antigo: cadastrado meses atrás, para os testes de atraso não
     * dependerem do dia em que rodam.
     */
    public function createdMonthsAgo(int $months): static
    {
        return $this->state(['created_at' => now()->subMonthsNoOverflow($months)]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
