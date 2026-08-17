<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $issuedAt = fake()->dateTimeBetween('-12 months', 'now');

        return [
            'client_id' => Client::factory(),
            'amount' => fake()->randomFloat(2, 1_200, 28_000),
            'issued_at' => $issuedAt,
            'paid_at' => fake()->boolean(75) ? $issuedAt : null,
        ];
    }
}
