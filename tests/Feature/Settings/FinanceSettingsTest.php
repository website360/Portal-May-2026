<?php

namespace Tests\Feature\Settings;

use App\Models\CostCenter;
use App\Models\FinanceCategory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FinanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_guests_can_not_reach_the_settings(): void
    {
        $this->app['auth']->logout();

        $this->get(route('configuracoes.centros.index'))->assertRedirect(route('login'));
        $this->get(route('configuracoes.categorias.index'))->assertRedirect(route('login'));
    }

    public function test_a_cost_center_can_be_created_and_edited(): void
    {
        $this->post(route('configuracoes.centros.index'), [
            'name' => 'Casa',
            'description' => 'Despesas pessoais',
            'color' => 'violet',
        ])->assertSessionHasNoErrors();

        $center = CostCenter::firstOrFail();
        $this->assertSame('violet', $center->color);
        $this->assertTrue($center->active);

        $this->put(route('configuracoes.centros.update', $center), [
            'name' => 'Casa',
            'color' => 'red',
            'active' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($center->refresh()->active);
    }

    public function test_the_cost_center_name_can_not_repeat(): void
    {
        CostCenter::factory()->create(['name' => 'Empresa']);

        $this->post(route('configuracoes.centros.index'), ['name' => 'Empresa', 'color' => 'blue'])->assertSessionHasErrors('name');
    }

    public function test_an_unknown_color_is_rejected(): void
    {
        $this->post(route('configuracoes.centros.index'), ['name' => 'Novo', 'color' => 'rosa-choque'])->assertSessionHasErrors('color');
    }

    public function test_the_list_shows_how_many_entries_use_each_center(): void
    {
        $center = CostCenter::factory()->create();
        Transaction::factory(3)->create(['cost_center_id' => $center->id]);

        $this->get(route('configuracoes.centros.index'))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('configuracoes/centros-de-custo')
                ->where('costCenters.0.transactions_count', 3)
                ->has('colors')
        );
    }

    public function test_a_category_can_be_created_per_nature(): void
    {
        $this->post(route('configuracoes.categorias.index'), ['name' => 'Aluguel', 'type' => 'expense', 'color' => 'amber'])->assertSessionHasNoErrors();
        $this->post(route('configuracoes.categorias.index'), ['name' => 'Projetos', 'type' => 'income', 'color' => 'green'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('finance_categories', 2);
    }

    /** Mesmo nome em naturezas diferentes é legítimo: "Serviços" entra e sai. */
    public function test_the_same_name_is_allowed_across_natures(): void
    {
        $this->post(route('configuracoes.categorias.index'), ['name' => 'Serviços', 'type' => 'expense', 'color' => 'blue'])->assertSessionHasNoErrors();
        $this->post(route('configuracoes.categorias.index'), ['name' => 'Serviços', 'type' => 'income', 'color' => 'blue'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('finance_categories', 2);
    }

    public function test_the_same_name_repeats_within_a_nature(): void
    {
        FinanceCategory::factory()->expense()->create(['name' => 'Aluguel']);

        $this->post(route('configuracoes.categorias.index'), ['name' => 'Aluguel', 'type' => 'expense', 'color' => 'blue'])->assertSessionHasErrors('name');
    }

    public function test_deleting_a_category_keeps_the_entries(): void
    {
        $category = FinanceCategory::factory()->expense()->create();
        $transaction = Transaction::factory()->create(['finance_category_id' => $category->id]);

        $this->delete(route('configuracoes.categorias.destroy', $category))->assertSessionHas('success');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'finance_category_id' => null]);
    }
}
