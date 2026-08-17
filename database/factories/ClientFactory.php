<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    private const SEGMENTS = [
        'Varejo',
        'Saúde',
        'Educação',
        'Tecnologia',
        'Alimentação',
        'Imobiliário',
        'Serviços financeiros',
        'Indústria',
        'Turismo',
        'Advocacia',
    ];

    private const ROLES = [
        'Sócio-proprietário',
        'Diretor de marketing',
        'Gerente comercial',
        'Coordenadora de marketing',
        'CEO',
        'Analista de comunicação',
    ];

    /**
     * O faker pt_BR nao tem gerador de bairro — `citySuffix()` devolveria
     * "do Norte", que e sufixo de cidade, nao bairro.
     */
    /**
     * O gerador de cidade do faker pt_BR combina prefixo e sufixo ("Santa Manoela
     * d'Oeste"), o que soa artificial numa demonstracao. Cidades reais, pareadas
     * com a UF certa, deixam a lista com cara de dado de verdade.
     *
     * @var list<array{string, string}>
     */
    private const CITIES = [
        ['São Paulo', 'SP'],
        ['Campinas', 'SP'],
        ['Santo André', 'SP'],
        ['Rio de Janeiro', 'RJ'],
        ['Niterói', 'RJ'],
        ['Belo Horizonte', 'MG'],
        ['Uberlândia', 'MG'],
        ['Curitiba', 'PR'],
        ['Londrina', 'PR'],
        ['Porto Alegre', 'RS'],
        ['Caxias do Sul', 'RS'],
        ['Florianópolis', 'SC'],
        ['Joinville', 'SC'],
        ['Salvador', 'BA'],
        ['Recife', 'PE'],
        ['Fortaleza', 'CE'],
        ['Brasília', 'DF'],
        ['Goiânia', 'GO'],
        ['Vitória', 'ES'],
        ['Belém', 'PA'],
    ];

    private const DISTRICTS = [
        'Centro',
        'Jardim América',
        'Vila Nova',
        'Bela Vista',
        'Santa Cecília',
        'Boa Viagem',
        'Alto da Lapa',
        'Moema',
        'Savassi',
        'Batel',
        'Meireles',
        'Petrópolis',
    ];

    public function definition(): array
    {
        $isCompany = fake()->boolean(75);
        $name = $isCompany ? fake()->company() : fake()->name();
        [$city, $state] = fake()->randomElement(self::CITIES);

        // Nome fantasia costuma ser o nucleo da razao social: "Alcantara e Leon"
        // -> "Alcantara". Quando sai igual ao nome inteiro, nao acrescenta nada.
        $tradeName = $isCompany ? $this->coreName($name) : null;

        return [
            'type' => $isCompany ? Client::TYPE_COMPANY : Client::TYPE_PERSON,
            'name' => $name,
            'trade_name' => $tradeName === $name ? null : $tradeName,
            'document' => $isCompany ? fake()->unique()->cnpj() : fake()->unique()->cpf(),
            'status' => fake()->boolean(80) ? Client::STATUS_ACTIVE : Client::STATUS_INACTIVE,

            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->cellphoneNumber(),
            'contact_name' => fake()->name(),
            'contact_role' => fake()->randomElement(self::ROLES),

            'zip_code' => fake()->postcode(),
            'street' => fake()->streetName(),
            'number' => (string) fake()->buildingNumber(),
            'complement' => fake()->boolean(35) ? 'Sala '.fake()->numberBetween(10, 900) : null,
            'district' => fake()->randomElement(self::DISTRICTS),
            'city' => $city,
            'state' => $state,

            'segment' => fake()->randomElement(self::SEGMENTS),
            'monthly_fee' => fake()->boolean(70) ? fake()->randomFloat(2, 900, 18_000) : null,
            'started_at' => fake()->dateTimeBetween('-3 years', 'now'),
            'notes' => fake()->boolean(30) ? fake()->sentence(12) : null,
        ];
    }

    /**
     * Primeira palavra significativa da razao social. Pular as particulas evita
     * que "da Rosa e Associados" vire o nome fantasia "da".
     */
    private function coreName(string $name): string
    {
        $particles = ['da', 'de', 'do', 'das', 'dos', 'e'];

        foreach (explode(' ', $name) as $word) {
            if (! in_array(mb_strtolower($word), $particles, true)) {
                return $word;
            }
        }

        return $name;
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => Client::STATUS_INACTIVE]);
    }

    public function person(): static
    {
        return $this->state(fn () => [
            'type' => Client::TYPE_PERSON,
            'name' => fake()->name(),
            'trade_name' => null,
            'document' => fake()->unique()->cpf(),
        ]);
    }
}
