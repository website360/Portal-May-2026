<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Client;
use App\Models\CostCenter;
use App\Models\Domain;
use App\Models\FinanceCategory;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Popula o banco com dados de demonstracao.
 *
 * Os dados sao ficticios, mas vivem em tabelas reais: o DashboardController faz
 * query de verdade contra elas. Quando os modulos reais chegarem, eles estendem
 * estas tabelas por migration e o dashboard continua funcionando sem alteracao.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Agência May',
            'email' => 'admin@agenciamay.com.br',
            'password' => Hash::make('password'),
        ]);

        /*
         * A equipe entra como usuária comum, com o acesso do dia a dia: trabalha
         * em tarefas e consulta clientes e domínios, sem alcançar o financeiro.
         * Serve também de exemplo de como as permissões se combinam.
         */
        $team = User::factory(4)->create([
            'permissions' => Permissions::sanitize([
                'dashboard' => Permissions::READ,
                'tarefas' => Permissions::WRITE,
                'clientes' => Permissions::READ,
                'dominios' => Permissions::READ,
            ]),
        ]);
        $everyone = $team->push($admin);

        $this->seedClients();
        $this->seedDomains();
        $this->seedInvoices();
        $this->seedTasks($everyone);
        $this->seedFinance();
        $this->seedActivities($everyone);
    }

    /**
     * Centros de custo, categorias e lançamentos. Os vencimentos cobrem o
     * trimestre em volta de hoje, com contas vencidas e pagas, para o painel
     * financeiro não nascer vazio.
     */
    private function seedFinance(): void
    {
        $centers = collect([
            ['name' => 'Empresa', 'description' => 'Operação da agência: clientes, equipe e impostos', 'color' => 'blue'],
            ['name' => 'Escritório', 'description' => 'Estrutura física: aluguel, contas e manutenção', 'color' => 'amber'],
            ['name' => 'Casa', 'description' => 'Despesas pessoais mantidas separadas do negócio', 'color' => 'violet'],
        ])->map(fn (array $center) => CostCenter::create($center));

        $income = collect([
            ['name' => 'Mensalidades', 'color' => 'green'],
            ['name' => 'Projetos', 'color' => 'sky'],
            ['name' => 'Consultoria', 'color' => 'violet'],
        ])->map(fn (array $category) => FinanceCategory::create([...$category, 'type' => FinanceCategory::TYPE_INCOME]));

        $expense = collect([
            ['name' => 'Aluguel', 'color' => 'amber'],
            ['name' => 'Software e assinaturas', 'color' => 'sky'],
            ['name' => 'Hospedagem e domínios', 'color' => 'blue'],
            ['name' => 'Impostos', 'color' => 'red'],
            ['name' => 'Equipe e freelancers', 'color' => 'violet'],
            ['name' => 'Marketing', 'color' => 'green'],
            ['name' => 'Contabilidade', 'color' => 'amber'],
            ['name' => 'Energia e internet', 'color' => 'sky'],
            ['name' => 'Supermercado', 'color' => 'green'],
        ])->map(fn (array $category) => FinanceCategory::create([...$category, 'type' => FinanceCategory::TYPE_EXPENSE]));

        $company = $centers->firstWhere('name', 'Empresa');
        $office = $centers->firstWhere('name', 'Escritório');
        $home = $centers->firstWhere('name', 'Casa');
        $clients = Client::inRandomOrder()->take(20)->get();

        // Contas fixas: mesma despesa todo mês, do trimestre passado ao próximo.
        $fixed = [
            ['Aluguel do escritório', 'Aluguel', $office, 4_200.00, 5],
            ['Energia e internet', 'Energia e internet', $office, 680.00, 12],
            ['Contabilidade', 'Contabilidade', $company, 890.00, 10],
            ['Adobe, Figma e afins', 'Software e assinaturas', $company, 1_240.00, 8],
            ['Servidor e domínios', 'Hospedagem e domínios', $company, 430.00, 15],
            ['Condomínio de casa', 'Aluguel', $home, 950.00, 7],
        ];

        foreach ($fixed as [$description, $categoryName, $center, $amount, $day]) {
            foreach (range(-3, 2) as $offset) {
                $due = Carbon::today()->startOfMonth()->addMonths($offset)->setDay($day);

                Transaction::factory()->payable()->create([
                    'description' => $description,
                    'amount' => $amount,
                    'due_date' => $due,
                    'cost_center_id' => $center->id,
                    'finance_category_id' => $expense->firstWhere('name', $categoryName)->id,
                    'counterpart' => null,
                    'paid_at' => $due->isPast() && ! $due->isToday() ? $due : null,
                    'paid_amount' => $due->isPast() && ! $due->isToday() ? $amount : null,
                ]);
            }
        }

        // Mensalidades a receber dos clientes com contrato.
        foreach ($clients->take(14) as $client) {
            foreach (range(-2, 1) as $offset) {
                $due = Carbon::today()->startOfMonth()->addMonths($offset)->setDay(random_int(5, 25));
                $paid = $due->isPast() && random_int(1, 100) <= 80;

                // Um valor só: recebido igual ao previsto. Divergência entre os
                // dois existe de verdade (juros, desconto), mas inventá-la aqui
                // só faria os indicadores parecerem que não fecham.
                $amount = round((float) ($client->monthly_fee ?? random_int(900, 6_000)), 2);

                Transaction::factory()->receivable()->create([
                    'description' => "Mensalidade {$client->name}",
                    'amount' => $amount,
                    'due_date' => $due,
                    'cost_center_id' => $company->id,
                    'finance_category_id' => $income->firstWhere('name', 'Mensalidades')->id,
                    'client_id' => $client->id,
                    'counterpart' => null,
                    'paid_at' => $paid ? $due : null,
                    'paid_amount' => $paid ? $amount : null,
                ]);
            }
        }

        // Avulsos, para haver vencido e a vencer em todos os centros.
        Transaction::factory(10)->payable()->overdue()->create([
            'cost_center_id' => fn () => $centers->random()->id,
            'finance_category_id' => fn () => $expense->random()->id,
        ]);

        Transaction::factory(8)->receivable()->overdue()->create([
            'cost_center_id' => $company->id,
            'finance_category_id' => fn () => $income->random()->id,
            'client_id' => fn () => $clients->random()->id,
        ]);

        Transaction::factory(16)->payable()->create([
            'due_date' => fn () => Carbon::today()->addDays(random_int(1, 60)),
            'cost_center_id' => fn () => $centers->random()->id,
            'finance_category_id' => fn () => $expense->random()->id,
        ]);
    }

    /**
     * Tarefas do dia a dia: a maioria solta, algumas ligadas a um cliente ou
     * projeto. Inclui atrasadas e concluídas hoje para os contadores da página
     * não nascerem zerados.
     *
     * @param  Collection<int, User>  $users
     */
    private function seedTasks(Collection $users): void
    {
        $clients = Client::inRandomOrder()->take(12)->get();
        $projects = Project::inRandomOrder()->take(10)->get();

        Task::factory(26)->create(['user_id' => fn () => $users->random()->id]);

        Task::factory(8)->overdue()->create([
            'user_id' => fn () => $users->random()->id,
            'client_id' => fn () => $clients->random()->id,
        ]);

        Task::factory(6)->doing()->create([
            'user_id' => fn () => $users->random()->id,
            'project_id' => fn () => $projects->random()->id,
        ]);

        Task::factory(5)->done()->create([
            'user_id' => fn () => $users->random()->id,
            'completed_at' => now()->subHours(random_int(1, 8)),
        ]);

        Task::factory(4)->create([
            'user_id' => fn () => $users->random()->id,
            'client_id' => fn () => $clients->random()->id,
            'priority' => Task::PRIORITY_URGENT,
            'status' => Task::STATUS_PENDING,
            'completed_at' => null,
            'due_date' => now()->addDays(random_int(0, 3)),
        ]);
    }

    /**
     * Quase todo cliente tem dominio, as vezes mais de um. A distribuicao de
     * vencimentos e proposital: alguns ja vencidos e alguns dentro da janela de
     * 30 dias, para o painel de renovacao ter o que mostrar.
     */
    private function seedDomains(): void
    {
        $used = [];

        foreach (Client::all() as $index => $client) {
            if (random_int(1, 100) > 92) {
                continue; // Uma minoria sem dominio nenhum.
            }

            foreach (range(1, random_int(1, 3)) as $position) {
                $name = $this->domainName($client->name, $position, $used);
                $used[] = $name;

                $factory = Domain::factory()->for($client)->state(['name' => $name]);

                // A cada bloco de clientes, uma amostra vencida e outra vencendo.
                $factory = match (true) {
                    $index % 11 === 0 && $position === 1 => $factory->managedByAgency()->expired(),
                    $index % 7 === 0 && $position === 1 => $factory->managedByAgency()->expiringIn(random_int(2, 28)),
                    $index % 13 === 0 => $factory->withoutExpiration(),
                    default => $factory,
                };

                $factory->create();
            }
        }
    }

    /**
     * @param  list<string>  $used
     */
    private function domainName(string $clientName, int $position, array $used): string
    {
        $base = Str::slug($this->coreName($clientName));
        $tld = $position === 1 ? '.com.br' : Arr::random(['.com', '.net.br', '.app.br']);

        $name = $base.$tld;
        $suffix = 1;

        while (in_array($name, $used, true)) {
            $name = $base.(++$suffix).$tld;
        }

        return $name;
    }

    /** Primeira palavra significativa da razão social, para virar o domínio. */
    private function coreName(string $name): string
    {
        $particles = ['da', 'de', 'do', 'das', 'dos', 'e', 'dr', 'dr.', 'dra', 'dra.', 'sr', 'sr.', 'sra', 'sra.'];

        foreach (explode(' ', $name) as $word) {
            if (! in_array(mb_strtolower($word), $particles, true)) {
                return $word;
            }
        }

        return $name;
    }

    /**
     * Clientes distribuidos ao longo dos ultimos 14 meses, cada um com projetos
     * e tarefas. O created_at espalhado e o que permite comparar o mes atual
     * com o anterior nos KPIs.
     */
    private function seedClients(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $createdAt = Carbon::now()->subDays(random_int(0, 430))->setTime(random_int(8, 18), random_int(0, 59));

            $client = Client::factory()->create([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $projects = Project::factory(random_int(1, 3))->create([
                'client_id' => $client->id,
            ])->each(function (Project $project) use ($createdAt) {
                $projectCreatedAt = $createdAt->copy()->addDays(random_int(0, 40));

                $project->forceFill([
                    'created_at' => $projectCreatedAt,
                    'updated_at' => $projectCreatedAt,
                ])->save();
            });

            foreach ($projects as $project) {
                // Poucas por projeto: a maior parte da lista de tarefas vem do
                // dia a dia, semeado em seedTasks().
                Task::factory(random_int(0, 2))->create([
                    'project_id' => $project->id,
                ])->each(function (Task $task) use ($project) {
                    $taskCreatedAt = $project->created_at->copy()->addDays(random_int(0, 30));

                    $task->forceFill([
                        'created_at' => $taskCreatedAt,
                        'updated_at' => $taskCreatedAt,
                    ])->save();
                });
            }
        }
    }

    /**
     * Doze meses de faturas, com tendencia de crescimento leve, para o grafico
     * de faturamento ter historia em vez de ruido.
     */
    private function seedInvoices(): void
    {
        $clientIds = Client::pluck('id')->all();

        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $month = Carbon::now()->startOfMonth()->subMonths($monthsAgo);
            $growth = 1 + ((11 - $monthsAgo) * 0.06);
            $invoicesThisMonth = random_int(6, 11);

            for ($i = 0; $i < $invoicesThisMonth; $i++) {
                $lastDay = $monthsAgo === 0
                    ? min(Carbon::now()->day, $month->daysInMonth)
                    : $month->daysInMonth;

                $issuedAt = $month->copy()->setDay(random_int(1, $lastDay));

                Invoice::factory()->create([
                    'client_id' => fake()->randomElement($clientIds),
                    'amount' => round(fake()->randomFloat(2, 2_500, 19_000) * $growth, 2),
                    'issued_at' => $issuedAt,
                    'paid_at' => fake()->boolean(78) ? $issuedAt->copy()->addDays(random_int(1, 25)) : null,
                    'created_at' => $issuedAt,
                    'updated_at' => $issuedAt,
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function seedActivities($users): void
    {
        for ($i = 0; $i < 12; $i++) {
            $createdAt = Carbon::now()->subHours($i * random_int(3, 9))->subMinutes(random_int(0, 59));

            Activity::factory()->create([
                'user_id' => $users->random()->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
