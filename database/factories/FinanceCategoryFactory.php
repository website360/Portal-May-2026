<?php

namespace Database\Factories;

use App\Models\CostCenter;
use App\Models\FinanceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceCategory>
 */
class FinanceCategoryFactory extends Factory
{
    protected $model = FinanceCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(FinanceCategory::TYPES),
            'color' => fake()->randomElement(CostCenter::COLORS),
            'active' => true,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => FinanceCategory::TYPE_INCOME]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => FinanceCategory::TYPE_EXPENSE]);
    }
}
