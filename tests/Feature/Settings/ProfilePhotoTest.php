<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create(['name' => 'Caio May', 'email' => 'caio@agenciamay.com.br']);
        $this->actingAs($this->user);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->user->name,
            'email' => $this->user->email,
        ], $overrides);
    }

    public function test_a_photo_can_be_uploaded(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('eu.jpg', 400, 400),
        ]))->assertSessionHasNoErrors();

        $this->user->refresh();

        $this->assertNotNull($this->user->photo_path);
        Storage::disk('public')->assertExists($this->user->photo_path);
    }

    public function test_the_photo_lands_in_its_own_folder(): void
    {
        // Fotos de usuário e de cliente não podem se misturar no disco.
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('eu.jpg'),
        ]));

        $this->assertStringStartsWith('users/', $this->user->refresh()->photo_path);
    }

    public function test_without_a_photo_the_avatar_is_null_so_the_front_falls_back_to_initials(): void
    {
        $this->assertNull($this->user->avatar);
        $this->assertArrayHasKey('avatar', $this->user->toArray());
    }

    /** A URL é derivada do caminho, nunca gravada. */
    public function test_the_avatar_is_a_relative_url_under_storage(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('eu.jpg'),
        ]));

        $url = $this->user->refresh()->avatar;

        $this->assertNotNull($url);
        $this->assertStringStartsWith('/storage/users/', $url);
    }

    public function test_the_photo_path_never_leaks_to_the_front(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('eu.jpg'),
        ]));

        $this->assertArrayNotHasKey('photo_path', $this->user->refresh()->toArray());
    }

    public function test_replacing_the_photo_deletes_the_old_file(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('primeira.jpg'),
        ]));

        $first = $this->user->refresh()->photo_path;

        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('segunda.jpg'),
        ]));

        $second = $this->user->refresh()->photo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_the_photo_can_be_removed(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('eu.jpg'),
        ]));

        $path = $this->user->refresh()->photo_path;

        $this->patch('/configuracoes/perfil', $this->payload(['remove_photo' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->user->refresh()->photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_saving_the_name_alone_keeps_the_photo(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('eu.jpg'),
        ]));

        $path = $this->user->refresh()->photo_path;

        $this->patch('/configuracoes/perfil', $this->payload(['name' => 'Outro Nome']))
            ->assertSessionHasNoErrors();

        $this->user->refresh();

        $this->assertSame('Outro Nome', $this->user->name);
        $this->assertSame($path, $this->user->photo_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('photo');

        $this->assertNull($this->user->refresh()->photo_path);
    }

    public function test_an_image_over_two_megabytes_is_rejected(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('enorme.jpg')->size(2049),
        ]))->assertSessionHasErrors('photo');
    }

    public function test_deleting_the_account_deletes_the_photo_file(): void
    {
        $this->patch('/configuracoes/perfil', $this->payload([
            'photo' => UploadedFile::fake()->image('eu.jpg'),
        ]));

        $path = $this->user->refresh()->photo_path;

        $this->delete('/configuracoes/perfil', ['password' => 'password'])
            ->assertRedirect('/');

        Storage::disk('public')->assertMissing($path);
    }

    /** O upload chega por POST com _method=patch, porque PHP não lê multipart em PATCH. */
    public function test_the_spoofed_patch_used_by_the_form_works(): void
    {
        $this->post('/configuracoes/perfil', $this->payload([
            '_method' => 'patch',
            'photo' => UploadedFile::fake()->image('eu.jpg'),
        ]))->assertSessionHasNoErrors();

        $this->assertNotNull($this->user->refresh()->photo_path);
    }
}
