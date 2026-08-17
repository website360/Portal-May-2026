<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Servicos tipicos de agencia, para a tabela de projetos recentes
     * parecer com o que a May realmente entrega.
     */
    private const SERVICES = [
        'Identidade visual',
        'Landing page',
        'Campanha de performance',
        'Gestão de redes sociais',
        'Rebranding',
        'E-commerce',
        'Vídeo institucional',
        'Consultoria de SEO',
        'Aplicativo mobile',
        'Automação de CRM',
    ];

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->randomElement(self::SERVICES),
            'status' => fake()->randomElement([
                Project::STATUS_IN_PROGRESS,
                Project::STATUS_IN_PROGRESS,
                Project::STATUS_COMPLETED,
                Project::STATUS_LATE,
            ]),
            'budget' => fake()->randomFloat(2, 3_500, 85_000),
            'due_date' => fake()->dateTimeBetween('-2 months', '+3 months'),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => Project::STATUS_IN_PROGRESS]);
    }
}
