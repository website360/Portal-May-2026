<?php

namespace Tests\Feature\Finance;

use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O aviso que importa não é "vence semana que vem" — é "esta é a última".
 *
 * Descobrir em agosto que setembro fecha o contrato dá tempo de renegociar; a
 * mesma notícia em setembro chega quando a cobrança já parou.
 */
class RecurrenceRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /** O caso descrito: contrato mensal fechando em setembro. */
    public function test_after_augusts_charge_the_system_knows_september_is_the_last(): void
    {
        $recurrence = Recurrence::factory()->monthly()->receivable()->create([
            'description' => 'Mensalidade — Vimacedo',
            'next_due_at' => '2026-08-10',
            'ends_at' => '2026-09-30',
        ]);

        // Em agosto ainda faltam duas: agosto e setembro.
        $this->assertSame(2, $recurrence->remaining());
        $this->assertFalse($recurrence->isLastCharge());
        $this->assertTrue($recurrence->isEnding());

        // Gerada a de agosto, a próxima é setembro — e é a última.
        $recurrence->generateNext();
        $recurrence->refresh();

        $this->assertSame('2026-09-10', $recurrence->next_due_at->toDateString());
        $this->assertSame(1, $recurrence->remaining());
        $this->assertTrue($recurrence->isLastCharge());
    }

    public function test_a_recurrence_without_an_end_date_never_reports_a_last_charge(): void
    {
        // Contrato que corre até alguém cancelar: não há "última" para avisar.
        $recurrence = Recurrence::factory()->monthly()->create(['ends_at' => null]);

        $this->assertNull($recurrence->remaining());
        $this->assertFalse($recurrence->isLastCharge());
        $this->assertFalse($recurrence->isEnding());
    }

    public function test_an_annual_contract_counts_in_years(): void
    {
        $recurrence = Recurrence::factory()->annual()->create([
            'next_due_at' => '2026-03-10',
            'ends_at' => '2029-03-10',
        ]);

        // 2026, 2027, 2028 e 2029.
        $this->assertSame(4, $recurrence->remaining());
        $this->assertFalse($recurrence->isEnding());
    }

    public function test_a_finished_recurrence_has_nothing_left(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'next_due_at' => '2026-10-10',
            'ends_at' => '2026-09-30',
        ]);

        $this->assertSame(0, $recurrence->remaining());
        $this->assertFalse($recurrence->isLastCharge());
        // Zerada não é "acabando": já acabou, e o aviso seria tarde.
        $this->assertFalse($recurrence->isEnding());
    }

    public function test_an_inactive_recurrence_has_nothing_left(): void
    {
        $recurrence = Recurrence::factory()->monthly()->inactive()->create([
            'next_due_at' => '2026-08-10',
            'ends_at' => '2026-12-31',
        ]);

        $this->assertSame(0, $recurrence->remaining());
    }

    public function test_renewing_pushes_the_end_date_by_the_asked_cycles(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'next_due_at' => '2026-09-10',
            'ends_at' => '2026-09-30',
        ]);

        $this->assertTrue($recurrence->isLastCharge());

        $recurrence->renew(12);
        $recurrence->refresh();

        $this->assertSame('2027-09-30', $recurrence->ends_at->toDateString());
        $this->assertSame(13, $recurrence->remaining());
        $this->assertFalse($recurrence->isLastCharge());
    }

    /** Renovar não pode mexer no que já foi cobrado nem no próximo vencimento. */
    public function test_renewing_leaves_the_next_due_date_and_the_history_alone(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'next_due_at' => '2026-08-10',
            'ends_at' => '2026-09-30',
            'amount' => 500,
        ]);

        $recurrence->generateNext();
        $emitida = Transaction::sole();

        $recurrence->refresh()->renew(12, 650);
        $recurrence->refresh();

        $this->assertSame('2026-09-10', $recurrence->next_due_at->toDateString());
        $this->assertSame('650.00', (string) $recurrence->amount);
        // A conta de agosto foi emitida a 500 e continua a 500.
        $this->assertSame('500.00', (string) $emitida->refresh()->amount);
    }

    public function test_the_new_amount_applies_only_to_charges_generated_after_the_renewal(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'next_due_at' => '2026-08-10',
            'ends_at' => '2026-12-31',
            'amount' => 500,
        ]);

        $recurrence->generateNext();
        $recurrence->refresh()->renew(12, 650);
        $recurrence->refresh()->generateNext();

        $valores = Transaction::orderBy('due_date')->pluck('amount')->map(fn ($v) => (string) $v)->all();

        $this->assertSame(['500.00', '650.00'], $valores);
    }

    /** Renovar uma que já tinha terminado volta a colocá-la para rodar. */
    public function test_renewing_a_finished_recurrence_brings_it_back(): void
    {
        $recurrence = Recurrence::factory()->monthly()->create([
            'next_due_at' => '2026-10-10',
            'ends_at' => '2026-09-30',
        ]);

        $this->assertTrue($recurrence->hasEnded());

        $recurrence->renew(6);
        $recurrence->refresh();

        $this->assertFalse($recurrence->hasEnded());
        $this->assertTrue($recurrence->isRunning());
        // Vencido: a contagem recomeça do próximo vencimento, e a primeira das
        // seis é ele mesmo — 10/10 mais cinco meses.
        $this->assertSame('2027-03-10', $recurrence->ends_at->toDateString());
    }

    public function test_the_ending_scope_finds_only_who_still_has_charges_left(): void
    {
        Recurrence::factory()->monthly()->create(['description' => 'Acabando', 'next_due_at' => '2026-09-10', 'ends_at' => '2026-09-30']);
        Recurrence::factory()->monthly()->create(['description' => 'Sem fim', 'ends_at' => null]);
        Recurrence::factory()->monthly()->create(['description' => 'Já acabou', 'next_due_at' => '2026-10-10', 'ends_at' => '2026-09-30']);
        Recurrence::factory()->monthly()->inactive()->create(['description' => 'Parada', 'next_due_at' => '2026-09-10', 'ends_at' => '2026-09-30']);

        $found = Recurrence::ending()->get();

        $this->assertSame(['Acabando'], $found->pluck('description')->all());
    }

    /** O aviso vale para o que a agência paga também, não só para o que recebe. */
    public function test_it_works_for_payables_too(): void
    {
        $recurrence = Recurrence::factory()->annual()->create([
            'description' => 'Hospedagem',
            'type' => Transaction::TYPE_PAYABLE,
            'next_due_at' => '2026-11-01',
            'ends_at' => '2026-12-31',
        ]);

        $this->assertTrue($recurrence->isLastCharge());
    }
}
