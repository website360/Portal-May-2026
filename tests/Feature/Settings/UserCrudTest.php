<?php

namespace Tests\Feature\Settings;

use App\Models\Task;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Caio']);
        $this->actingAs($this->admin);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Souza',
            'email' => 'maria@agenciamay.com.br',
            'password' => 'senha-bem-longa',
            'password_confirmation' => 'senha-bem-longa',
            'role' => User::ROLE_MEMBER,
            'permissions' => ['clientes' => Permissions::WRITE, 'tarefas' => Permissions::READ],
        ], $overrides);
    }

    public function test_the_list_renders_with_the_modules_and_levels(): void
    {
        $this->get('/configuracoes/usuarios')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('configuracoes/usuarios')
                    ->has('users', 1)
                    ->where('users.0.is_me', true)
                    ->has('modules')
                    ->has('levels', 3)
            );
    }

    public function test_a_user_can_be_created_with_permissions(): void
    {
        $this->post('/configuracoes/usuarios', $this->payload())->assertSessionHasNoErrors();

        $user = User::where('email', 'maria@agenciamay.com.br')->sole();

        $this->assertSame(User::ROLE_MEMBER, $user->role);
        $this->assertTrue(Hash::check('senha-bem-longa', $user->password));
        $this->assertSame(Permissions::WRITE, $user->permissions['clientes']);
        $this->assertSame(Permissions::READ, $user->permissions['tarefas']);
        // Módulo não enviado entra explicitamente como "sem acesso".
        $this->assertSame(Permissions::NONE, $user->permissions['financeiro']);
    }

    public function test_an_admin_stores_no_permission_map(): void
    {
        // Guardar um mapa cheio para quem passa por cima dele criaria duas
        // fontes da mesma verdade.
        $this->post('/configuracoes/usuarios', $this->payload([
            'role' => User::ROLE_ADMIN,
            'permissions' => ['clientes' => Permissions::READ],
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'maria@agenciamay.com.br')->sole();

        $this->assertNull($user->permissions);
        $this->assertTrue($user->allows('financeiro', Permissions::WRITE));
    }

    public function test_the_email_can_not_repeat(): void
    {
        $this->post('/configuracoes/usuarios', $this->payload(['email' => $this->admin->email]))
            ->assertSessionHasErrors('email');
    }

    public function test_a_new_user_needs_a_password(): void
    {
        $this->post('/configuracoes/usuarios', $this->payload(['password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors('password');
    }

    public function test_an_unconfirmed_password_is_rejected(): void
    {
        $this->post('/configuracoes/usuarios', $this->payload(['password_confirmation' => 'outra-coisa']))
            ->assertSessionHasErrors('password');
    }

    public function test_permissions_can_be_changed(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MEMBER, 'permissions' => Permissions::all(Permissions::WRITE)]);

        $this->put("/configuracoes/usuarios/{$user->id}", $this->payload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
            'permissions' => ['financeiro' => Permissions::READ],
        ]))->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame(Permissions::READ, $user->permissions['financeiro']);
        $this->assertSame(Permissions::NONE, $user->permissions['clientes']);
    }

    public function test_an_empty_password_on_edit_keeps_the_current_one(): void
    {
        $user = User::factory()->create();
        $before = $user->password;

        $this->put("/configuracoes/usuarios/{$user->id}", $this->payload([
            'name' => 'Nome Novo',
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
        ]))->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Nome Novo', $user->name);
        $this->assertSame($before, $user->password);
    }

    public function test_the_password_can_be_reset_by_an_admin(): void
    {
        $user = User::factory()->create();

        $this->put("/configuracoes/usuarios/{$user->id}", $this->payload([
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'nova-senha-longa',
            'password_confirmation' => 'nova-senha-longa',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nova-senha-longa', $user->refresh()->password));
    }

    public function test_a_user_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $this->delete("/configuracoes/usuarios/{$user->id}")->assertSessionHasNoErrors();

        $this->assertNull($user->fresh());
    }

    /** Histórico de trabalho não sai junto com quem deixou a agência. */
    public function test_deleting_a_user_keeps_their_tasks_without_an_owner(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Ajustar banner']);

        $this->delete("/configuracoes/usuarios/{$user->id}");

        $task->refresh();

        $this->assertSame('Ajustar banner', $task->title);
        $this->assertNull($task->user_id);
    }

    public function test_you_can_not_delete_your_own_account_here(): void
    {
        $this->delete("/configuracoes/usuarios/{$this->admin->id}")
            ->assertSessionHasErrors('usuario');

        $this->assertNotNull($this->admin->fresh());
    }

    /** Rebaixar o último administrador deixaria o sistema sem quem edite permissões. */
    public function test_the_last_admin_can_not_be_demoted(): void
    {
        $this->put("/configuracoes/usuarios/{$this->admin->id}", $this->payload([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => User::ROLE_MEMBER,
        ]))->assertSessionHasErrors('role');

        $this->assertTrue($this->admin->refresh()->isAdmin());
    }

    public function test_an_admin_can_be_demoted_once_another_one_exists(): void
    {
        User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->put("/configuracoes/usuarios/{$this->admin->id}", $this->payload([
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => User::ROLE_MEMBER,
        ]))->assertSessionHasNoErrors();

        $this->assertFalse($this->admin->refresh()->isAdmin());
    }

    public function test_the_last_admin_can_not_be_deleted(): void
    {
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);

        // Com dois administradores, excluir um é permitido...
        $this->delete("/configuracoes/usuarios/{$other->id}")->assertSessionHasNoErrors();

        // ...e agora o que sobrou é o último; quem tenta excluí-lo é outro admin.
        $ultimo = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->admin->update(['role' => User::ROLE_MEMBER]);
        $this->actingAs($ultimo);

        $this->delete("/configuracoes/usuarios/{$ultimo->id}")->assertSessionHasErrors('usuario');
        $this->assertNotNull($ultimo->fresh());
    }

    public function test_an_invalid_permission_level_is_rejected(): void
    {
        $this->post('/configuracoes/usuarios', $this->payload([
            'permissions' => ['clientes' => 'superusuario'],
        ]))->assertSessionHasErrors('permissions.clientes');
    }
}
