<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    private const REGISTRARS = ['Registro.br', 'GoDaddy', 'Hostinger', 'Namecheap', 'Cloudflare', 'HostGator'];

    public function definition(): array
    {
        $registeredAt = fake()->dateTimeBetween('-5 years', '-6 months');

        return [
            'client_id' => Client::factory(),
            'name' => fake()->unique()->domainName(),
            'registrar' => fake()->randomElement(self::REGISTRARS),
            'managed_by' => fake()->boolean(65) ? Domain::MANAGED_BY_AGENCY : Domain::MANAGED_BY_CLIENT,
            'registered_at' => $registeredAt,
            'expires_at' => fake()->dateTimeBetween('-1 month', '+2 years'),
            'auto_renew' => fake()->boolean(45),
            'annual_cost' => fake()->boolean(80) ? fake()->randomFloat(2, 40, 320) : null,
            'notes' => fake()->boolean(20) ? fake()->sentence(8) : null,
        ];
    }

    public function managedByAgency(): static
    {
        return $this->state(fn () => ['managed_by' => Domain::MANAGED_BY_AGENCY]);
    }

    public function managedByClient(): static
    {
        return $this->state(fn () => ['managed_by' => Domain::MANAGED_BY_CLIENT]);
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn () => ['expires_at' => now()->addDays($days)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDays(random_int(1, 60))]);
    }

    public function withoutExpiration(): static
    {
        return $this->state(fn () => ['expires_at' => null]);
    }
}
