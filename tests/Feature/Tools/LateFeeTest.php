<?php

namespace Tests\Feature\Tools;

use App\Models\User;
use App\Support\LateFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A conta do boleto atrasado.
 *
 * O que sai daqui vira cobrança para o cliente, então cada regra tem caso
 * próprio — inclusive as bordas, que é onde arredondamento e contagem de dias
 * costumam errar em silêncio.
 */
class LateFeeTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/ferramentas/boleto';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    private function calcular(string $vencimento, string $pagamento, float $valor = 1000, float $multa = 2, float $juros = 1, float $desconto = 0): array
    {
        return LateFee::calculate($valor, Carbon::parse($vencimento), Carbon::parse($pagamento), $multa, $juros, $desconto);
    }

    // ── Contagem de dias ─────────────────────────────────────────────────────

    /** O dia do vencimento é do devedor: pagar nele é estar em dia. */
    public function test_paying_on_the_due_date_is_not_late(): void
    {
        $r = $this->calcular('2026-08-10', '2026-08-10');

        $this->assertFalse($r['late']);
        $this->assertSame(0, $r['days_late']);
        $this->assertSame(0.0, $r['fine']);
        $this->assertSame(0.0, $r['interest']);
        $this->assertSame(1000.0, $r['total']);
    }

    public function test_paying_early_costs_nothing_extra(): void
    {
        $r = $this->calcular('2026-08-10', '2026-08-01');

        $this->assertSame(0, $r['days_late']);
        $this->assertSame(1000.0, $r['total']);
    }

    public function test_a_single_day_late_already_counts(): void
    {
        $r = $this->calcular('2026-08-10', '2026-08-11');

        $this->assertTrue($r['late']);
        $this->assertSame(1, $r['days_late']);
    }

    /** Dias corridos, e não úteis: a contagem atravessa meses e fins de semana. */
    public function test_the_count_crosses_months(): void
    {
        $this->assertSame(52, $this->calcular('2026-07-10', '2026-08-31')['days_late']);
        $this->assertSame(365, $this->calcular('2025-08-10', '2026-08-10')['days_late']);
    }

    // ── Multa e juros ────────────────────────────────────────────────────────

    /**
     * A multa entra uma vez; os juros crescem por dia.
     *
     * R$ 1.000 com 2% e 1% ao mês, 30 dias: R$ 20 de multa e R$ 10 de juros.
     */
    public function test_the_fine_is_charged_once_and_interest_by_the_day(): void
    {
        $r = $this->calcular('2026-07-10', '2026-08-09');

        $this->assertSame(30, $r['days_late']);
        $this->assertSame(20.0, $r['fine']);
        $this->assertSame(10.0, $r['interest']);
        $this->assertSame(1030.0, $r['total']);
    }

    /** Meio mês de atraso cobra metade do juro mensal. */
    public function test_half_a_month_charges_half_the_monthly_interest(): void
    {
        $r = $this->calcular('2026-07-10', '2026-07-25');

        $this->assertSame(15, $r['days_late']);
        $this->assertSame(5.0, $r['interest']);
    }

    /** Dois meses cobram o dobro: o juro é simples, não composto. */
    public function test_the_interest_is_simple_not_compound(): void
    {
        $r = $this->calcular('2026-06-10', '2026-08-09', 1000);

        $this->assertSame(60, $r['days_late']);
        $this->assertSame(20.0, $r['interest']);
        // Composto daria 20,10 — a praxe do boleto é simples.
        $this->assertSame(1040.0, $r['total']);
    }

    public function test_the_percentages_are_free(): void
    {
        $r = $this->calcular('2026-07-10', '2026-08-09', 1000, multa: 5, juros: 3);

        $this->assertSame(50.0, $r['fine']);
        $this->assertSame(30.0, $r['interest']);
    }

    /** Sem multa e sem juros combinados, atraso não acrescenta nada. */
    public function test_zero_percentages_add_nothing(): void
    {
        $r = $this->calcular('2026-07-10', '2026-08-09', 1000, multa: 0, juros: 0);

        $this->assertTrue($r['late']);
        $this->assertSame(1000.0, $r['total']);
    }

    // ── Desconto ─────────────────────────────────────────────────────────────

    public function test_the_discount_comes_off_the_total(): void
    {
        $r = $this->calcular('2026-07-10', '2026-08-09', 1000, desconto: 30);

        $this->assertSame(30.0, $r['discount']);
        $this->assertSame(1000.0, $r['total']);
    }

    /** Desconto maior que a dívida zera a conta, não vira troco. */
    public function test_a_discount_never_becomes_change(): void
    {
        $r = $this->calcular('2026-08-10', '2026-08-10', 100, desconto: 500);

        $this->assertSame(0.0, $r['total']);
        $this->assertSame(100.0, $r['discount']);
    }

    // ── Centavos ─────────────────────────────────────────────────────────────

    /**
     * Centavo não pode sumir no arredondamento.
     *
     * R$ 1.234,56 com 2% dá R$ 24,6912 — que vira 24,69, e não 24,68.
     */
    public function test_cents_round_the_way_money_rounds(): void
    {
        $r = $this->calcular('2026-07-10', '2026-08-09', 1234.56);

        $this->assertSame(24.69, $r['fine']);
        $this->assertSame(12.35, $r['interest']);
        $this->assertSame(1271.60, $r['total']);
    }

    public function test_the_daily_rate_is_reported(): void
    {
        $r = $this->calcular('2026-07-10', '2026-08-09', 1000, juros: 1);

        // 1% ao mês rateado em trinta dias.
        $this->assertEqualsWithDelta(0.033333, $r['daily_rate'], 0.000001);
    }

    // ── A tela ───────────────────────────────────────────────────────────────

    public function test_the_tools_page_lists_the_calculator(): void
    {
        $this->get('/ferramentas')->assertOk()->assertInertia(
            fn ($page) => $page->component('ferramentas/index')->where('tools.0.slug', 'boleto')
        );
    }

    public function test_the_calculator_page_opens(): void
    {
        $this->get(self::URL)->assertOk()->assertInertia(fn ($page) => $page->component('ferramentas/boleto')->has('today'));
    }

    // ── Vários boletos ───────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function serie(int $quantidade, string $primeiro = '2026-05-08', string $pagamento = '2026-08-17', array $extra = []): array
    {
        return LateFee::schedule(
            $extra['valor'] ?? 1000,
            Carbon::parse($primeiro),
            $quantidade,
            Carbon::parse($pagamento),
            $extra['multa'] ?? 5,
            $extra['juros'] ?? 1,
            $extra['desconto'] ?? 0,
        );
    }

    /** Um boleto por mês, a partir do primeiro vencimento. */
    public function test_the_due_dates_walk_month_by_month(): void
    {
        $r = $this->serie(4);

        $this->assertSame(
            ['2026-05-08', '2026-06-08', '2026-07-08', '2026-08-08'],
            array_column($r['installments'], 'due_at')
        );
    }

    /** Dia 31 não escorrega para o mês seguinte quando o mês é curto. */
    public function test_the_thirty_first_lands_on_the_last_day_of_a_short_month(): void
    {
        $r = $this->serie(3, primeiro: '2026-01-31');

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_column($r['installments'], 'due_at'));
    }

    /** Cada boleto carrega o seu próprio atraso: o mais antigo pesa mais. */
    public function test_each_one_carries_its_own_delay(): void
    {
        $r = $this->serie(4);

        $this->assertSame([101, 70, 40, 9], array_column($r['installments'], 'days_late'));
        $this->assertGreaterThan($r['installments'][3]['total'], $r['installments'][0]['total']);
    }

    /** O total é a soma das parcelas, e não a conta feita sobre o valor somado. */
    public function test_the_total_is_the_sum_of_the_parts(): void
    {
        $r = $this->serie(4);

        $this->assertSame(4, $r['totals']['count']);
        $this->assertSame(4, $r['totals']['late_count']);
        $this->assertSame(4000.0, $r['totals']['amount']);
        $this->assertSame(200.0, $r['totals']['fine']);
        $this->assertSame(
            array_sum(array_column($r['installments'], 'total')),
            $r['totals']['total']
        );
    }

    /** Boleto que ainda não venceu entra na série sem acréscimo. */
    public function test_an_installment_that_has_not_come_due_adds_nothing(): void
    {
        $r = $this->serie(4, primeiro: '2026-07-08', pagamento: '2026-08-17');

        $this->assertSame(2, $r['totals']['late_count']);
        $this->assertSame(0.0, $r['installments'][2]['fine']);
        $this->assertSame(1000.0, $r['installments'][2]['total']);
    }

    /** O desconto é um só, abatido do total — não se repete em cada parcela. */
    public function test_the_discount_comes_off_the_grand_total_once(): void
    {
        $com = $this->serie(4, extra: ['desconto' => 100]);
        $sem = $this->serie(4);

        $this->assertSame(100.0, $com['totals']['discount']);
        $this->assertSame(round($sem['totals']['total'] - 100, 2), $com['totals']['total']);
    }

    public function test_a_single_boleto_is_a_series_of_one(): void
    {
        $r = $this->serie(1, primeiro: '2026-07-10', pagamento: '2026-08-09', extra: ['multa' => 2]);

        $this->assertCount(1, $r['installments']);
        $this->assertSame(30, $r['installments'][0]['days_late']);
        $this->assertSame(1030.0, $r['totals']['total']);
    }

    // ── A tela ───────────────────────────────────────────────────────────────

    public function test_the_endpoint_answers_the_screen(): void
    {
        $this->getJson(self::URL.'/calculo?'.http_build_query([
            'amount' => 1000,
            'due_at' => '2026-07-10',
            'paid_at' => '2026-08-09',
            'fine' => 2,
            'interest' => 1,
        ]))->assertOk()->assertJson([
            'installments' => [['days_late' => 30, 'fine' => 20, 'interest' => 10, 'total' => 1030]],
            'totals' => ['count' => 1, 'total' => 1030],
        ]);
    }

    public function test_the_endpoint_takes_a_quantity(): void
    {
        $this->getJson(self::URL.'/calculo?'.http_build_query([
            'amount' => 1000,
            'due_at' => '2026-05-08',
            'paid_at' => '2026-08-17',
            'count' => 4,
        ]))->assertOk()->assertJsonCount(4, 'installments')->assertJson([
            'totals' => ['count' => 4, 'late_count' => 4, 'amount' => 4000, 'fine' => 200],
        ]);
    }

    /** 0,0333% ao dia e 1% ao mês são a mesma taxa, escrita de dois jeitos. */
    public function test_the_daily_rate_is_the_monthly_one_over_thirty(): void
    {
        $pergunta = fn (array $extra) => $this->getJson(self::URL.'/calculo?'.http_build_query([
            'amount' => 1000,
            'due_at' => '2026-07-10',
            'paid_at' => '2026-08-09',
            'fine' => 0,
        ] + $extra))->json('totals.interest');

        $this->assertEqualsWithDelta(10.0, $pergunta(['interest' => 1, 'interest_unit' => 'month']), 0.001);
        $this->assertEqualsWithDelta(10.0, $pergunta(['interest' => 1 / 30, 'interest_unit' => 'day']), 0.001);
    }

    public function test_the_endpoint_refuses_an_absurd_quantity(): void
    {
        $this->getJson(self::URL.'/calculo?'.http_build_query([
            'amount' => 1000,
            'due_at' => '2026-05-08',
            'paid_at' => '2026-08-17',
            'count' => 500,
        ]))->assertStatus(422);
    }

    public function test_the_endpoint_demands_the_essentials(): void
    {
        $this->getJson(self::URL.'/calculo')->assertStatus(422);
    }

    public function test_someone_without_the_module_does_not_get_in(): void
    {
        $this->actingAs(User::factory()->member()->create(['permissions' => ['ferramentas' => 'none']]))
            ->get('/ferramentas')
            ->assertForbidden();
    }
}
