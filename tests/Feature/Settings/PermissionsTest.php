<?php

namespace Tests\Feature\Settings;

use App\Models\Client;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uma tela de permissões que não barra nada é pior do que não ter permissão
 * nenhuma: passa a sensação de controle sem o controle. Estes testes existem
 * para provar que a checagem acontece no servidor.
 */
class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $permissions
     */
    private function member(array $permissions = []): User
    {
        return User::factory()->member($permissions)->create();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_without_permission_the_page_is_forbidden(): void
    {
        $this->actingAs($this->member())
            ->get('/clientes')
            ->assertForbidden();
    }

    public function test_read_permission_opens_the_page(): void
    {
        $this->actingAs($this->member(['clientes' => Permissions::READ]))
            ->get('/clientes')
            ->assertOk();
    }

    public function test_read_permission_does_not_allow_writing(): void
    {
        $this->actingAs($this->member(['clientes' => Permissions::READ]))
            ->from('/clientes')
            ->post('/clientes', ['type' => Client::TYPE_COMPANY, 'name' => 'Nova', 'status' => Client::STATUS_ACTIVE])
            ->assertSessionHasErrors('permissao');

        $this->assertSame(0, Client::count());
    }

    public function test_write_permission_allows_writing(): void
    {
        $this->actingAs($this->member(['clientes' => Permissions::WRITE]))
            ->post('/clientes', ['type' => Client::TYPE_COMPANY, 'name' => 'Nova', 'status' => Client::STATUS_ACTIVE])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Client::count());
    }

    /** Sem leitura, nem a tentativa de gravar deve virar mensagem amigável. */
    public function test_without_any_access_a_write_is_forbidden_outright(): void
    {
        $this->actingAs($this->member())
            ->post('/clientes', ['type' => Client::TYPE_COMPANY, 'name' => 'Nova', 'status' => Client::STATUS_ACTIVE])
            ->assertForbidden();
    }

    public function test_permission_in_one_module_does_not_leak_into_another(): void
    {
        $user = $this->member(['clientes' => Permissions::WRITE]);

        $this->actingAs($user)->get('/clientes')->assertOk();
        $this->actingAs($user)->get('/financeiro')->assertForbidden();
        $this->actingAs($user)->get('/dominios')->assertForbidden();
        $this->actingAs($user)->get('/tarefas')->assertForbidden();
    }

    /** Sub-rotas herdam a permissão do módulo — `clientes.show` é `clientes`. */
    public function test_a_nested_route_is_covered_by_the_module_permission(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->member())
            ->get("/clientes/{$client->id}")
            ->assertForbidden();

        $this->actingAs($this->member(['clientes' => Permissions::READ]))
            ->get("/clientes/{$client->id}")
            ->assertOk();
    }

    /** O seletor inline de situação também é escrita. */
    public function test_the_inline_status_picker_requires_write(): void
    {
        $client = Client::factory()->create(['status' => Client::STATUS_ACTIVE]);

        $this->actingAs($this->member(['clientes' => Permissions::READ]))
            ->from('/clientes')
            ->patch("/clientes/{$client->id}/situacao", ['status' => Client::STATUS_INACTIVE])
            ->assertSessionHasErrors('permissao');

        $this->assertSame(Client::STATUS_ACTIVE, $client->refresh()->status);
    }

    public function test_an_admin_reaches_everything_without_a_permission_map(): void
    {
        $admin = $this->admin();

        foreach (['/dashboard', '/clientes', '/dominios', '/tarefas', '/financeiro', '/configuracoes/usuarios'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    /**
     * Nem escrita em Configurações abre a tela de usuários para quem não é
     * administrador: quem entra ali pode se promover, e isso transformaria
     * aquela permissão num atalho para acesso total.
     */
    public function test_only_an_admin_manages_users(): void
    {
        foreach ([Permissions::WRITE, Permissions::READ, Permissions::NONE] as $level) {
            $this->actingAs($this->member(['configuracoes' => $level]))
                ->get('/configuracoes/usuarios')
                ->assertForbidden();
        }

        $this->actingAs($this->admin())
            ->get('/configuracoes/usuarios')
            ->assertOk();
    }

    public function test_a_member_can_not_promote_themselves(): void
    {
        $user = $this->member(['configuracoes' => Permissions::WRITE]);

        $this->actingAs($user)
            ->put("/configuracoes/usuarios/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'role' => User::ROLE_ADMIN,
            ])
            ->assertForbidden();

        $this->assertFalse($user->refresh()->isAdmin());
    }

    public function test_a_member_can_not_create_users(): void
    {
        $this->actingAs($this->member(['configuracoes' => Permissions::WRITE]))
            ->post('/configuracoes/usuarios', [
                'name' => 'Intruso',
                'email' => 'intruso@exemplo.com',
                'password' => 'senha-bem-longa',
                'password_confirmation' => 'senha-bem-longa',
                'role' => User::ROLE_ADMIN,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@exemplo.com']);
    }

    /** As outras telas de Configurações seguem valendo por permissão comum. */
    public function test_the_finance_settings_still_answer_to_the_module_permission(): void
    {
        $this->actingAs($this->member(['configuracoes' => Permissions::READ]))
            ->get('/configuracoes/financeiro/centros-de-custo')
            ->assertOk();

        $this->actingAs($this->member())
            ->get('/configuracoes/financeiro/centros-de-custo')
            ->assertForbidden();
    }

    /** Ninguém pode ser trancado para fora da própria conta. */
    public function test_personal_pages_never_depend_on_permission(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get('/configuracoes/perfil')->assertOk();
        $this->actingAs($user)->get('/configuracoes/senha')->assertOk();
        $this->actingAs($user)->get('/configuracoes/aparencia')->assertOk();
        $this->actingAs($user)->post('/logout')->assertRedirect();
    }

    public function test_the_front_receives_the_resolved_permission_map(): void
    {
        $this->actingAs($this->member(['clientes' => Permissions::READ]))
            ->get('/configuracoes/perfil')
            ->assertInertia(
                fn ($page) => $page
                    ->where('auth.permissions.clientes', Permissions::READ)
                    ->where('auth.permissions.financeiro', Permissions::NONE)
                    ->where('auth.isAdmin', false)
            );
    }

    public function test_an_admin_map_comes_fully_granted(): void
    {
        $this->actingAs($this->admin())
            ->get('/configuracoes/perfil')
            ->assertInertia(
                fn ($page) => $page
                    ->where('auth.permissions.financeiro', Permissions::WRITE)
                    ->where('auth.isAdmin', true)
            );
    }

    /** Nível inventado vira "sem acesso", nunca acesso. */
    public function test_an_unknown_level_is_treated_as_no_access(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'permissions' => ['clientes' => 'superusuario'],
        ]);

        $this->actingAs($user)->get('/clientes')->assertForbidden();
    }

    public function test_a_module_missing_from_the_map_is_treated_as_no_access(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MEMBER, 'permissions' => []]);

        $this->actingAs($user)->get('/financeiro')->assertForbidden();
    }
}
