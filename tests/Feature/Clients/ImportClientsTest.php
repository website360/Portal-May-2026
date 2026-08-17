<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportClientsTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'id,user_id,client_type,full_name,company_name,cpf_cnpj,responsible_name,'
        .'email,phone,birth_date,gender,address_cep,address_street,address_number,address_complement,'
        .'address_neighborhood,address_city,address_state,is_active,created_at,updated_at,created_by,'
        .'nickname,responsible_cpf';

    private string $path;

    protected function tearDown(): void
    {
        if (isset($this->path) && file_exists($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    /**
     * @param  array<int, string>  $rows
     */
    private function csv(array $rows, string $header = self::HEADER): string
    {
        $this->path = tempnam(sys_get_temp_dir(), 'clientes').'.csv';
        file_put_contents($this->path, $header."\n".implode("\n", $rows)."\n");

        return $this->path;
    }

    /**
     * Uma linha de empresa, com os campos que importam nomeados.
     *
     * @param  array<string, string>  $overrides
     */
    private function company(array $overrides = []): string
    {
        $row = array_merge([
            'client_type' => 'company',
            'full_name' => 'null',
            'company_name' => 'Vimacedo Comércio Ltda',
            'cpf_cnpj' => '26.704.752/0004-70',
            'responsible_name' => 'Vimacedo',
            'email' => 'tadeu@vimacedo.com.br',
            'phone' => '(11) 91264-3430',
            'address_cep' => '02147-900',
            'address_street' => 'Rua Cabo Norberto Enrique Weber',
            'address_number' => '222',
            'address_complement' => 'Galpão 33',
            'address_neighborhood' => 'Parque Novo Mundo',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
            'is_active' => 'true',
            'created_at' => '2025-10-14 16:40:42.361192+00',
        ], $overrides);

        return implode(',', [
            'uuid', 'null', $row['client_type'], $row['full_name'], $row['company_name'],
            $row['cpf_cnpj'], $row['responsible_name'], $row['email'], $row['phone'],
            'null', 'null', $row['address_cep'], $row['address_street'], $row['address_number'],
            $row['address_complement'], $row['address_neighborhood'], $row['address_city'],
            $row['address_state'], $row['is_active'], $row['created_at'],
            $row['created_at'], 'null', 'null', 'null',
        ]);
    }

    public function test_it_maps_every_column_the_old_system_provides(): void
    {
        $this->artisan('clientes:importar', ['arquivo' => $this->csv([$this->company()])])
            ->assertSuccessful();

        $client = Client::sole();

        $this->assertSame(Client::TYPE_COMPANY, $client->type);
        $this->assertSame('Vimacedo Comércio Ltda', $client->name);
        $this->assertSame('26.704.752/0004-70', $client->document);
        $this->assertSame('tadeu@vimacedo.com.br', $client->email);
        $this->assertSame('(11) 91264-3430', $client->phone);
        $this->assertSame(Client::STATUS_ACTIVE, $client->status);
        $this->assertSame('02147-900', $client->zip_code);
        $this->assertSame('Rua Cabo Norberto Enrique Weber', $client->street);
        $this->assertSame('222', $client->number);
        $this->assertSame('Galpão 33', $client->complement);
        $this->assertSame('Parque Novo Mundo', $client->district);
        $this->assertSame('São Paulo', $client->city);
        $this->assertSame('SP', $client->state);
        $this->assertSame('2025-10-14', $client->started_at->toDateString());
    }

    public function test_responsible_name_becomes_the_trade_name(): void
    {
        // A coluna se chama "responsável", mas guarda marca — "Vimacedo" para
        // "Vimacedo Comércio Ltda", "Casa Mui" para uma pessoa física.
        $this->artisan('clientes:importar', ['arquivo' => $this->csv([$this->company()])]);

        $this->assertSame('Vimacedo', Client::sole()->trade_name);
    }

    public function test_a_person_takes_the_name_from_the_other_column(): void
    {
        $row = $this->company([
            'client_type' => 'person',
            'full_name' => 'Talita Justino de Medeiros Maia',
            'company_name' => 'null',
            'cpf_cnpj' => '329.565.018-79',
        ]);

        $this->artisan('clientes:importar', ['arquivo' => $this->csv([$row])]);

        $client = Client::sole();

        $this->assertSame(Client::TYPE_PERSON, $client->type);
        $this->assertSame('Talita Justino de Medeiros Maia', $client->name);
    }

    public function test_a_person_keeps_the_brand_they_trade_under(): void
    {
        // Autônomo com marca própria é comum na carteira: "Casa Mui",
        // "By Artees", "O Rei da Caipirinha".
        $row = $this->company([
            'client_type' => 'person',
            'full_name' => 'Anna Carolina Matroni Benson',
            'company_name' => 'null',
            'cpf_cnpj' => '459.710.608-13',
            'responsible_name' => 'By Artees',
        ]);

        $this->artisan('clientes:importar', ['arquivo' => $this->csv([$row])]);

        $this->assertSame('By Artees', Client::sole()->trade_name);
    }

    public function test_it_repairs_text_that_was_exported_with_the_wrong_encoding(): void
    {
        $row = $this->company([
            'company_name' => 'IrmÃ£os Mantovani Textil Ltda',
            'address_city' => 'SÃ£o Paulo',
            'address_neighborhood' => 'Vila CarrÃ£o',
        ]);

        $this->artisan('clientes:importar', ['arquivo' => $this->csv([$row])]);

        $client = Client::sole();

        $this->assertSame('Irmãos Mantovani Textil Ltda', $client->name);
        $this->assertSame('São Paulo', $client->city);
        $this->assertSame('Vila Carrão', $client->district);
    }

    public function test_text_that_is_already_correct_is_left_alone(): void
    {
        $row = $this->company(['company_name' => 'Comércio de Ração São João Ltda']);

        $this->artisan('clientes:importar', ['arquivo' => $this->csv([$row])]);

        $this->assertSame('Comércio de Ração São João Ltda', Client::sole()->name);
    }

    public function test_the_literal_word_null_is_read_as_empty(): void
    {
        $row = $this->company([
            'address_cep' => 'null',
            'address_street' => 'null',
            'address_city' => 'null',
        ]);

        $this->artisan('clientes:importar', ['arquivo' => $this->csv([$row])]);

        $client = Client::sole();

        $this->assertNull($client->zip_code);
        $this->assertNull($client->street);
        $this->assertNull($client->city);
    }

    public function test_inactive_in_the_old_system_stays_inactive_here(): void
    {
        $this->artisan('clientes:importar', [
            'arquivo' => $this->csv([$this->company(['is_active' => 'false'])]),
        ]);

        $this->assertSame(Client::STATUS_INACTIVE, Client::sole()->status);
    }

    public function test_running_it_twice_does_not_duplicate_anyone(): void
    {
        $file = $this->csv([$this->company()]);

        $this->artisan('clientes:importar', ['arquivo' => $file]);
        $this->artisan('clientes:importar', ['arquivo' => $file])
            ->expectsOutputToContain('já cadastrado');

        $this->assertSame(1, Client::count());
    }

    public function test_the_same_document_twice_in_one_file_only_lands_once(): void
    {
        // A coluna é única no banco: sem esta trava a carga quebraria no meio.
        $rows = [
            $this->company(),
            $this->company(['company_name' => 'Vimacedo Filial']),
        ];

        $this->artisan('clientes:importar', ['arquivo' => $this->csv($rows)])
            ->assertSuccessful();

        $this->assertSame(1, Client::count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('clientes:importar', [
            'arquivo' => $this->csv([$this->company()]),
            '--dry-run' => true,
        ])->expectsOutputToContain('SIMULAÇÃO');

        $this->assertSame(0, Client::count());
    }

    public function test_atualizar_refreshes_who_is_already_here(): void
    {
        $file = $this->csv([$this->company()]);
        $this->artisan('clientes:importar', ['arquivo' => $file]);

        Client::sole()->update(['city' => 'Cidade Errada']);

        $this->artisan('clientes:importar', ['arquivo' => $file, '--atualizar' => true]);

        $this->assertSame(1, Client::count());
        $this->assertSame('São Paulo', Client::sole()->city);
    }

    public function test_a_row_without_a_name_is_reported_instead_of_imported(): void
    {
        $row = $this->company(['company_name' => 'null', 'full_name' => 'null']);

        $this->artisan('clientes:importar', ['arquivo' => $this->csv([$row])])
            ->expectsOutputToContain('sem nome');

        $this->assertSame(0, Client::count());
    }

    public function test_it_refuses_a_file_that_is_not_the_expected_export(): void
    {
        $file = $this->csv(['Fulano,fulano@example.com'], 'nome,email');

        $this->artisan('clientes:importar', ['arquivo' => $file])
            ->assertFailed()
            ->expectsOutputToContain('Faltam as colunas');

        $this->assertSame(0, Client::count());
    }

    public function test_it_fails_cleanly_when_the_file_is_not_there(): void
    {
        $this->artisan('clientes:importar', ['arquivo' => 'nao/existe.csv'])
            ->assertFailed();
    }

    public function test_limpar_removes_whoever_is_not_in_the_file(): void
    {
        $antigo = Client::factory()->create(['name' => 'Cliente de demonstração']);

        $this->artisan('clientes:importar', [
            'arquivo' => $this->csv([$this->company()]),
            '--limpar' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Client::count());
        $this->assertSame('Vimacedo Comércio Ltda', Client::sole()->name);
        $this->assertDatabaseMissing('clients', ['id' => $antigo->id]);
    }

    public function test_limpar_keeps_who_came_in_the_file_even_when_only_skipped(): void
    {
        $file = $this->csv([$this->company()]);

        // Primeira carga cria; a segunda pula por já existir. Pular não pode
        // significar "não veio no arquivo" — senão a limpeza apagaria a carga.
        $this->artisan('clientes:importar', ['arquivo' => $file]);

        $this->artisan('clientes:importar', [
            'arquivo' => $file,
            '--limpar' => true,
            '--force' => true,
        ]);

        $this->assertSame(1, Client::count());
    }

    public function test_limpar_takes_the_dependent_records_with_it(): void
    {
        $antigo = Client::factory()->create();
        $antigo->domains()->create([
            'name' => 'exemplo.com.br',
            'managed_by_us' => true,
            'expires_at' => now()->addYear(),
        ]);

        $this->artisan('clientes:importar', [
            'arquivo' => $this->csv([$this->company()]),
            '--limpar' => true,
            '--force' => true,
        ])->expectsOutputToContain('vão junto: 1 domínios');

        $this->assertSame(0, Domain::count());
    }

    public function test_limpar_in_dry_run_deletes_nobody(): void
    {
        Client::factory()->count(3)->create();

        $this->artisan('clientes:importar', [
            'arquivo' => $this->csv([$this->company()]),
            '--limpar' => true,
            '--dry-run' => true,
        ])->expectsOutputToContain('SIMULAÇÃO');

        $this->assertSame(3, Client::count());
    }

    public function test_limpar_asks_before_deleting(): void
    {
        Client::factory()->count(2)->create();

        $this->artisan('clientes:importar', [
            'arquivo' => $this->csv([$this->company()]),
            '--limpar' => true,
        ])
            ->expectsConfirmation('Apagar?', 'no')
            ->expectsOutputToContain('Limpeza cancelada');

        $this->assertSame(3, Client::count());
    }

    public function test_internal_and_test_rows_are_flagged_for_review(): void
    {
        $rows = [
            $this->company([
                'client_type' => 'person',
                'full_name' => 'Sistema — Transferência Interna',
                'company_name' => 'null',
                'cpf_cnpj' => 'null',
                'email' => 'sistema@interno.local',
                'phone' => '0000000000',
            ]),
            $this->company(['email' => 'teste@teste.com.br']),
        ];

        $this->artisan('clientes:importar', ['arquivo' => $this->csv($rows)])
            ->expectsOutputToContain('registro interno ou de teste');
    }
}
