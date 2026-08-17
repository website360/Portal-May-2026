<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session_and_returns_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_the_root_sends_visitors_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /**
     * Sistema interno: nao existe cadastro publico, recuperacao de senha nem
     * verificacao de e-mail. Estes testes travam a decisao.
     */
    #[DataProvider('removedAuthRoutes')]
    public function test_public_signup_and_password_recovery_routes_are_gone(string $uri): void
    {
        $this->get($uri)->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function removedAuthRoutes(): array
    {
        return [
            'registro' => ['/register'],
            'esqueci minha senha' => ['/forgot-password'],
            'redefinir senha' => ['/reset-password/token-qualquer'],
            'verificar e-mail' => ['/verify-email'],
        ];
    }

    public function test_removed_auth_routes_have_no_named_route(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('password.request'));
        $this->assertFalse(Route::has('password.reset'));
        $this->assertFalse(Route::has('verification.notice'));
    }
}
