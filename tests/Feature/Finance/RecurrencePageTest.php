<?php

namespace Tests\Feature\Finance;

use App\Models\Client;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RecurrencePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_lists_the_recurrences_with_what_is_left(): void
    {
        // trade_name explícito: o rótulo é a marca, e o factory sorteia uma.
        $client = Client::factory()->create(['name' => 'Vimacedo Comércio Ltda', 'trade_name' => 'Vimacedo']);

        Recurrence::factory()->monthly()->receivable()->create([
            'description' => 'Mensalidade',
            'client_id' => $client->id,
            'next_due_at' => '2026-09-10',
            'ends_at' => '2026-09-30',
        ]);

        $this->get('/financeiro/recorrencias')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('financeiro/recorrencias')
                    ->has('recurrences', 1)
                    ->where('recurrences.0.description', 'Mensalidade')
                    ->where('recurrences.0.client.name', 'Vimacedo')
                    ->where('recurrences.0.remaining', 1)
                    ->where('recurrences.0.is_last', true)
                    ->where('stats.ending', 1)
            );
    }

    /** A rota da lista não pode ser engolida por `financeiro/{lancamento}`. */
    public function test_the_list_route_is_not_captured_by_the_transaction_route(): void
    {
        $this->get('/financeiro/recorrencias')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('financeiro/recorrencias'));
    }

    public function test_renewing_extends_the_contract_from_the_page(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'next_due_at' => '2026-09-10',
            'ends_at' => '2026-09-30',
            'amount' => 500,
        ]);

        $this->post("/financeiro/recorrencias/{$recurrence->id}/renovar", ['cycles' => 12, 'amount' => '650.00'])
            ->assertSessionHasNoErrors();

        $recurrence->refresh();

        $this->assertSame('2027-09-30', $recurrence->ends_at->toDateString());
        $this->assertSame('650.00', (string) $recurrence->amount);
        // As cobranças do novo período já foram lançadas, então o próximo
        // vencimento andou até o fim da fila.
        $this->assertSame(13, Transaction::count());
    }

    /**
     * Renovar tem de lançar as cobranças que passaram a caber.
     *
     * Estender só a data deixava o contrato renovado e o financeiro sem nada de
     * novo — o botão parecia não ter feito nada.
     */
    public function test_renewing_lands_the_new_charges(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'amount' => 500,
            'next_due_at' => '2026-08-10',
            'ends_at' => '2026-08-31',
        ]);

        $recurrence->generateNext();
        $this->assertSame(1, Transaction::count());

        $this->post("/financeiro/recorrencias/{$recurrence->id}/renovar", ['cycles' => 2, 'amount' => '300.00'])
            ->assertSessionHasNoErrors();

        // Setembro e outubro entraram, já com o valor novo.
        $this->assertSame(3, Transaction::count());

        $valores = Transaction::orderBy('due_date')->pluck('amount')->map(fn ($v) => (string) $v)->all();

        $this->assertSame(['500.00', '300.00', '300.00'], $valores);
    }

    public function test_renewing_without_a_new_amount_keeps_the_current_one(): void
    {
        $recurrence = Recurrence::factory()->annual()->create(['amount' => 1200, 'ends_at' => now()->addMonth()->toDateString()]);

        $this->post("/financeiro/recorrencias/{$recurrence->id}/renovar", ['cycles' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('1200.00', (string) $recurrence->refresh()->amount);
    }

    public function test_the_cycles_must_be_at_least_one(): void
    {
        $recurrence = Recurrence::factory()->create();

        $this->post("/financeiro/recorrencias/{$recurrence->id}/renovar", ['cycles' => 0])
            ->assertSessionHasErrors('cycles');
    }

    public function test_a_recurrence_can_be_edited(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create(['description' => 'Antigo', 'amount' => 100]);

        $this->put("/financeiro/recorrencias/{$recurrence->id}", [
            'description' => 'Novo nome',
            'amount' => '250.00',
            'interval' => Recurrence::QUARTERLY,
            'next_due_at' => '2026-10-01',
            'active' => false,
        ])->assertSessionHasNoErrors();

        $recurrence->refresh();

        $this->assertSame('Novo nome', $recurrence->description);
        $this->assertSame(Recurrence::QUARTERLY, $recurrence->interval);
        $this->assertFalse($recurrence->active);
    }

    /** Encerrar para de gerar, mas não apaga o que já foi cobrado. */
    public function test_deleting_a_recurrence_keeps_the_charges_already_issued(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create(['description' => 'Hospedagem']);
        $recurrence->generateNext();

        $transaction = Transaction::sole();

        $this->delete("/financeiro/recorrencias/{$recurrence->id}")->assertSessionHasNoErrors();

        $this->assertNull($recurrence->fresh());
        $this->assertNotNull($transaction->fresh());
        $this->assertNull($transaction->fresh()->recurrence_id);
    }

    /** A lista herda a permissão do módulo financeiro. */
    public function test_it_answers_to_the_finance_permission(): void
    {
        $this->actingAs(User::factory()->member(['financeiro' => Permissions::READ])->create())
            ->get('/financeiro/recorrencias')
            ->assertOk();

        $this->actingAs(User::factory()->member()->create())
            ->get('/financeiro/recorrencias')
            ->assertForbidden();
    }

    public function test_read_only_can_not_renew(): void
    {
        $recurrence = Recurrence::factory()->create(['ends_at' => now()->addMonth()->toDateString()]);
        $before = $recurrence->ends_at->toDateString();

        $this->actingAs(User::factory()->member(['financeiro' => Permissions::READ])->create())
            ->from('/financeiro/recorrencias')
            ->post("/financeiro/recorrencias/{$recurrence->id}/renovar", ['cycles' => 12])
            ->assertSessionHasErrors('permissao');

        $this->assertSame($before, $recurrence->refresh()->ends_at->toDateString());
    }

    public function test_the_dashboard_warns_about_contracts_that_are_ending(): void
    {
        Recurrence::factory()->monthly()->create([
            'description' => 'Mensalidade — Vimacedo',
            'next_due_at' => now()->addDays(5)->toDateString(),
            'ends_at' => now()->addDays(20)->toDateString(),
        ]);

        Recurrence::factory()->monthly()->create(['description' => 'Sem fim', 'ends_at' => null]);

        $this->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('endingRecurrences', 1)
                ->where('endingRecurrences.0.description', 'Mensalidade — Vimacedo')
                ->where('endingRecurrences.0.is_last', true)
        );
    }

    public function test_the_dashboard_stays_quiet_when_nothing_is_ending(): void
    {
        Recurrence::factory()->annual()->create(['ends_at' => now()->addYears(5)->toDateString()]);

        $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->has('endingRecurrences', 0));
    }
}
