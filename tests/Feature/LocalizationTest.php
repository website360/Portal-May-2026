<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O .env pede pt_BR desde o início, mas sem os arquivos em lang/ o Laravel cai
 * no fallback e devolve erro de formulário em inglês — num sistema que é todo
 * em português. Estes testes prendem isso.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_runs_in_brazilian_portuguese(): void
    {
        $this->assertSame('pt_BR', config('app.locale'));
    }

    public function test_validation_errors_come_in_portuguese(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/clientes', ['type' => Client::TYPE_COMPANY, 'status' => Client::STATUS_ACTIVE])
            ->assertSessionHasErrors('name');

        $this->assertSame('O campo nome é obrigatório.', session('errors')->first('name'));
    }

    public function test_the_field_name_comes_from_the_form_request(): void
    {
        // ClientRequest chama a coluna de "nome fantasia", não de "trade name".
        $this->actingAs(User::factory()->create())
            ->post('/clientes', [
                'type' => Client::TYPE_COMPANY,
                'name' => 'Alfa',
                'status' => Client::STATUS_ACTIVE,
                'trade_name' => str_repeat('a', 300),
            ])
            ->assertSessionHasErrors('trade_name');

        $this->assertStringContainsString('nome fantasia', session('errors')->first('trade_name'));
    }

    public function test_a_duplicate_document_explains_itself_in_portuguese(): void
    {
        $this->actingAs(User::factory()->create());

        Client::factory()->create(['document' => '11.111.111/0001-11']);

        $this->post('/clientes', [
            'type' => Client::TYPE_COMPANY,
            'name' => 'Beta',
            'status' => Client::STATUS_ACTIVE,
            'document' => '11.111.111/0001-11',
        ])->assertSessionHasErrors('document');

        $this->assertStringNotContainsString('已', session('errors')->first('document'));
        $this->assertMatchesRegularExpression('/já|documento/iu', session('errors')->first('document'));
    }

    public function test_a_failed_login_answers_in_portuguese(): void
    {
        User::factory()->create(['email' => 'caio@agenciamay.com.br']);

        $this->from('/login')
            ->post('/login', ['email' => 'caio@agenciamay.com.br', 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->assertSame('E-mail ou senha incorretos.', session('errors')->first('email'));
    }

    public function test_the_wrong_password_when_deleting_the_account_answers_in_portuguese(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/configuracoes/perfil')
            ->delete('/configuracoes/perfil', ['password' => 'errada'])
            ->assertSessionHasErrors('password');

        $this->assertSame('A senha está incorreta.', session('errors')->first('password'));
    }
}
