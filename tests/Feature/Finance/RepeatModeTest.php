<?php

namespace Tests\Feature\Finance;

use App\Models\CostCenter;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parcelado e recorrente parecem a mesma coisa e não são.
 *
 * Parcelado fatia uma dívida que já existe inteira: as doze contas nascem hoje,
 * e ninguém renova nada. Recorrente é compromisso que se renova: só a próxima
 * cobrança vira conta, e o contrato precisa ser avisado antes de acabar.
 *
 * Estes testes prendem essa diferença no que o formulário produz.
 */
class RepeatModeTest extends TestCase
{
    use RefreshDatabase;

    private CostCenter $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->center = CostCenter::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => Transaction::TYPE_RECEIVABLE,
            'description' => 'Mensalidade',
            'amount' => '500.00',
            'due_date' => '2026-08-10',
            'cost_center_id' => $this->center->id,
        ], $overrides);
    }

    public function test_once_creates_a_single_transaction_and_no_recurrence(): void
    {
        $this->post('/financeiro', $this->payload(['repeat' => 'once']))->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, Recurrence::count());
    }

    /** Parcelado: a dívida inteira entra agora. */
    public function test_installments_create_every_charge_up_front(): void
    {
        $this->post('/financeiro', $this->payload(['repeat' => 'installments', 'installments' => 12]))
            ->assertSessionHasNoErrors();

        $this->assertSame(12, Transaction::count());
        $this->assertSame(0, Recurrence::count());

        // Nasceram juntas e dá para reconhecer a série.
        $this->assertSame(1, Transaction::distinct('series_id')->count('series_id'));
        $this->assertStringContainsString('(1/12)', Transaction::orderBy('due_date')->first()->description);
    }

    /** Recorrente: a regra nasce, e as cobranças do contrato já entram. */
    public function test_recurring_creates_the_rule_and_every_charge_of_the_contract(): void
    {
        $this->post('/financeiro', $this->payload([
            'repeat' => 'recurring',
            'interval' => Recurrence::MONTHLY,
            'occurrences' => 12,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(12, Transaction::count());
        $this->assertSame(1, Recurrence::count());

        $recurrence = Recurrence::sole();

        $this->assertSame(Recurrence::MONTHLY, $recurrence->interval);
        // 12 cobranças a partir de 10/08/2026: a última é 10/07/2027.
        $this->assertSame('2027-07-10', $recurrence->ends_at->toDateString());

        $datas = Transaction::orderBy('due_date')->pluck('due_date')->map(fn ($d) => $d->toDateString());

        $this->assertSame('2026-08-10', $datas->first());
        $this->assertSame('2027-07-10', $datas->last());
        $this->assertSame(12, Transaction::where('recurrence_id', $recurrence->id)->count());
    }

    /**
     * A cobrança de contrato não é parcela: não tem série, então excluir uma não
     * oferece "excluir todas". Mas é numerada, para a listagem mostrar 02/12.
     */
    public function test_a_recurring_charge_is_numbered_but_is_not_an_installment_series(): void
    {
        $this->post('/financeiro', $this->payload([
            'repeat' => 'recurring',
            'interval' => Recurrence::MONTHLY,
            'occurrences' => 12,
        ]));

        $segunda = Transaction::orderBy('due_date')->skip(1)->first();

        $this->assertNull($segunda->series_id);
        $this->assertSame(2, $segunda->installment);
        $this->assertSame(12, $segunda->installments);
        // A descrição não recebe "(2/12)" — a numeração é coluna, não texto.
        $this->assertSame('Mensalidade', $segunda->description);
    }

    public function test_a_recurring_charge_carries_the_classification(): void
    {
        $this->post('/financeiro', $this->payload([
            'repeat' => 'recurring',
            'interval' => Recurrence::ANNUAL,
            'counterpart' => 'Locaweb',
        ]));

        $transaction = Transaction::orderBy('due_date')->first();

        $this->assertSame(Transaction::TYPE_RECEIVABLE, $transaction->type);
        $this->assertSame($this->center->id, $transaction->cost_center_id);
        $this->assertSame('Locaweb', $transaction->counterpart);
    }

    public function test_recurring_demands_an_interval(): void
    {
        $this->post('/financeiro', $this->payload(['repeat' => 'recurring']))
            ->assertSessionHasErrors('interval');

        $this->assertSame(0, Recurrence::count());
    }

    /**
     * A mensagem padrão do required_if sai como "obrigatório quando repeat for
     * recurring" — cita um campo que a pessoa nunca viu e um valor em inglês.
     */
    public function test_the_missing_interval_is_explained_in_plain_portuguese(): void
    {
        $this->post('/financeiro', $this->payload(['repeat' => 'recurring']))
            ->assertSessionHasErrors('interval');

        $mensagem = session('errors')->first('interval');

        $this->assertStringNotContainsString('repeat', $mensagem);
        $this->assertStringNotContainsString('recurring', $mensagem);
        $this->assertSame('Escolha de quanto em quanto tempo a cobrança se repete.', $mensagem);
    }

    public function test_a_contract_needs_at_least_one_charge(): void
    {
        $this->post('/financeiro', $this->payload([
            'repeat' => 'recurring',
            'interval' => Recurrence::MONTHLY,
            'occurrences' => 0,
        ]))->assertSessionHasErrors('occurrences');

        $this->assertSame(0, Recurrence::count());
    }

    /**
     * A data de fim é calculada a partir da quantidade — 12 cobranças mensais a
     * partir de 10/08 fecham em 10/07 do ano seguinte, e não em 10/08.
     */
    public function test_the_end_date_is_derived_from_how_many_charges_were_asked(): void
    {
        $casos = [
            [Recurrence::MONTHLY, 12, '2027-07-10'],
            [Recurrence::MONTHLY, 1, '2026-08-10'],
            [Recurrence::QUARTERLY, 4, '2027-05-10'],
            [Recurrence::ANNUAL, 3, '2028-08-10'],
        ];

        foreach ($casos as [$interval, $occurrences, $esperado]) {
            Recurrence::query()->delete();
            Transaction::query()->delete();

            $this->post('/financeiro', $this->payload([
                'repeat' => 'recurring',
                'interval' => $interval,
                'occurrences' => $occurrences,
            ]))->assertSessionHasNoErrors();

            $this->assertSame($esperado, Recurrence::sole()->ends_at->toDateString(), "intervalo {$interval}, {$occurrences} cobranças");
        }
    }

    /** Pedida uma cobrança só, ela sai e o contrato já está encerrado. */
    public function test_a_single_occurrence_ends_right_after_the_first_charge(): void
    {
        $this->post('/financeiro', $this->payload([
            'repeat' => 'recurring',
            'interval' => Recurrence::MONTHLY,
            'occurrences' => 1,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, Recurrence::sole()->remaining());
    }

    public function test_recurring_without_an_end_date_runs_open_ended(): void
    {
        $this->post('/financeiro', $this->payload(['repeat' => 'recurring', 'interval' => Recurrence::ANNUAL]))
            ->assertSessionHasNoErrors();

        $recurrence = Recurrence::sole();

        $this->assertNull($recurrence->ends_at);
        // Sem fim não há "última cobrança" para avisar.
        $this->assertNull($recurrence->remaining());
    }

    /** O caso do contrato de um ano: doze cobranças, e a última avisa. */
    /**
     * Lançadas as doze, não sobra nada por gerar — e é aí que o contrato entra
     * na fila de renovação: `remaining` zerado com data de fim marcada.
     */
    public function test_a_twelve_month_contract_lands_every_charge_and_has_none_left_to_generate(): void
    {
        $this->post('/financeiro', $this->payload([
            'repeat' => 'recurring',
            'interval' => Recurrence::MONTHLY,
            'occurrences' => 12,
        ]));

        $this->assertSame(12, Transaction::count());
        $this->assertSame(0, Recurrence::sole()->remaining());
        $this->assertSame(12, Transaction::whereNotNull('recurrence_id')->count());
    }

    /** Sem escolher nada, o comportamento antigo continua: lançamento único. */
    public function test_omitting_the_repeat_mode_creates_a_single_transaction(): void
    {
        $this->post('/financeiro', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertSame(0, Recurrence::count());
    }

    /**
     * Quem manda só `installments`, sem declarar o modo, continua parcelando.
     * Exigir o modo novo transformaria doze parcelas em um lançamento só, em
     * silêncio.
     */
    public function test_installments_alone_still_mean_installments(): void
    {
        $this->post('/financeiro', $this->payload(['installments' => 3]))->assertSessionHasNoErrors();

        $this->assertSame(3, Transaction::count());
        $this->assertSame(0, Recurrence::count());
    }

    /** Pedir parcelamento com uma parcela só é lançamento único. */
    public function test_one_installment_is_just_a_single_transaction(): void
    {
        $this->post('/financeiro', $this->payload(['repeat' => 'installments', 'installments' => 1]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Transaction::count());
        $this->assertNull(Transaction::sole()->series_id);
    }
}
