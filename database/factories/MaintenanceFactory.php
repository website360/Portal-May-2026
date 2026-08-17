<?php

namespace Database\Factories;

use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Support\MaintenanceChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Maintenance>
 */
class MaintenanceFactory extends Factory
{
    protected $model = Maintenance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_plan_id' => MaintenancePlan::factory(),
            'user_id' => User::factory(),
            'performed_at' => now()->toDateString(),
            'items' => MaintenanceChecklist::blank(),
            'notes' => null,
        ];
    }
}
