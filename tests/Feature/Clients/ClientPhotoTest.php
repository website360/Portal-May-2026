<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => Client::TYPE_COMPANY,
            'name' => 'Padaria do Bairro Ltda.',
            'status' => Client::STATUS_ACTIVE,
        ], $overrides);
    }

    public function test_a_client_can_be_created_with_a_photo(): void
    {
        $this->post('/clientes', $this->payload([
            'photo' => UploadedFile::fake()->image('logo.jpg', 400, 400),
        ]))->assertSessionHasNoErrors();

        $client = Client::firstOrFail();

        $this->assertNotNull($client->photo_path);
        Storage::disk('public')->assertExists($client->photo_path);
    }

    public function test_a_client_without_a_photo_keeps_the_column_null(): void
    {
        $this->post('/clientes', $this->payload())->assertSessionHasNoErrors();

        $this->assertNull(Client::firstOrFail()->photo_path);
    }

    public function test_the_photo_url_is_null_without_a_photo(): void
    {
        $client = Client::factory()->create(['photo_path' => null]);

        $this->assertNull($client->photo_url);
    }

    public function test_a_non_image_is_rejected(): void
    {
        $this->post('/clientes', $this->payload([
            'photo' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_an_oversized_image_is_rejected(): void
    {
        $this->post('/clientes', $this->payload([
            'photo' => UploadedFile::fake()->image('enorme.jpg')->size(3000),
        ]))->assertSessionHasErrors('photo');
    }

    public function test_replacing_the_photo_deletes_the_previous_file(): void
    {
        $client = Client::factory()->create([
            'photo_path' => UploadedFile::fake()->image('antiga.jpg')->store('clients', 'public'),
        ]);

        $old = $client->photo_path;

        $this->post("/clientes/{$client->id}", $this->payload([
            '_method' => 'put',
            'photo' => UploadedFile::fake()->image('nova.jpg'),
        ]))->assertSessionHasNoErrors();

        $client->refresh();

        $this->assertNotSame($old, $client->photo_path);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($client->photo_path);
    }

    public function test_the_photo_can_be_removed(): void
    {
        $client = Client::factory()->create([
            'photo_path' => UploadedFile::fake()->image('antiga.jpg')->store('clients', 'public'),
        ]);

        $old = $client->photo_path;

        $this->post("/clientes/{$client->id}", $this->payload([
            '_method' => 'put',
            'remove_photo' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($client->refresh()->photo_path);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_editing_without_touching_the_photo_keeps_it(): void
    {
        $client = Client::factory()->create([
            'photo_path' => UploadedFile::fake()->image('atual.jpg')->store('clients', 'public'),
        ]);

        $kept = $client->photo_path;

        $this->post("/clientes/{$client->id}", $this->payload([
            '_method' => 'put',
            'name' => 'Nome novo',
        ]))->assertSessionHasNoErrors();

        $client->refresh();

        $this->assertSame('Nome novo', $client->name);
        $this->assertSame($kept, $client->photo_path);
        Storage::disk('public')->assertExists($kept);
    }

    public function test_deleting_a_client_removes_its_photo_file(): void
    {
        $client = Client::factory()->create([
            'photo_path' => UploadedFile::fake()->image('logo.jpg')->store('clients', 'public'),
        ]);

        $path = $client->photo_path;

        $this->delete("/clientes/{$client->id}")->assertSessionHas('success');

        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_listing_exposes_the_photo_url(): void
    {
        Client::factory()->create([
            'name' => 'Com foto',
            'photo_path' => UploadedFile::fake()->image('logo.jpg')->store('clients', 'public'),
        ]);

        // A URL precisa ser relativa ao host: derivada de APP_URL, ela apontaria
        // para a porta errada sempre que a aplicacao subisse em outro endereco.
        $this->get('/clientes')->assertInertia(
            fn ($page) => $page->where('clients.data.0.photo_url', fn ($url) => is_string($url) && str_starts_with($url, '/storage/clients/'))
        );
    }
}
