<?php

namespace Tests\Feature\Domains;

use App\Models\Client;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * O aviso de vencimento é o motivo do módulo existir, então a regra de quando
 * um domínio "precisa de atenção" tem teste próprio.
 */
class DomainExpirationTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->actingAs(User::factory()->create());
    }

    private function domainExpiringIn(?int $days, string $managedBy = Domain::MANAGED_BY_AGENCY): Domain
    {
        return Domain::factory()->for($this->client)->create([
            'managed_by' => $managedBy,
            'expires_at' => $days === null ? null : now()->addDays($days),
        ]);
    }

    public function test_the_status_follows_the_thirty_day_window(): void
    {
        $this->assertSame(Domain::STATUS_EXPIRED, $this->domainExpiringIn(-1)->status());
        $this->assertSame(Domain::STATUS_EXPIRING, $this->domainExpiringIn(0)->status());
        $this->assertSame(Domain::STATUS_EXPIRING, $this->domainExpiringIn(30)->status());
        $this->assertSame(Domain::STATUS_OK, $this->domainExpiringIn(31)->status());
        $this->assertSame(Domain::STATUS_UNKNOWN, $this->domainExpiringIn(null)->status());
    }

    public function test_days_left_is_signed(): void
    {
        $this->assertSame(10, $this->domainExpiringIn(10)->daysLeft());
        $this->assertSame(-5, $this->domainExpiringIn(-5)->daysLeft());
        $this->assertNull($this->domainExpiringIn(null)->daysLeft());
    }

    public function test_the_sql_filter_matches_the_computed_status(): void
    {
        $this->domainExpiringIn(-3);
        $this->domainExpiringIn(10);
        $this->domainExpiringIn(90);
        $this->domainExpiringIn(null);

        $this->assertSame(1, Domain::query()->withStatus(Domain::STATUS_EXPIRED)->count());
        $this->assertSame(1, Domain::query()->withStatus(Domain::STATUS_EXPIRING)->count());
        $this->assertSame(1, Domain::query()->withStatus(Domain::STATUS_OK)->count());
        $this->assertSame(1, Domain::query()->withStatus(Domain::STATUS_UNKNOWN)->count());
    }

    /**
     * As bordas da janela são o ponto frágil: o filtro SQL compara texto contra
     * uma coluna `date` gravada com hora, então o último dia some fácil.
     */
    public function test_the_sql_filter_holds_at_the_window_edges(): void
    {
        $this->domainExpiringIn(0);
        $this->domainExpiringIn(30);
        $this->domainExpiringIn(31);
        $this->domainExpiringIn(-1);

        $this->assertSame(2, Domain::query()->withStatus(Domain::STATUS_EXPIRING)->count(), 'dias 0 e 30 devem entrar na janela');
        $this->assertSame(1, Domain::query()->withStatus(Domain::STATUS_OK)->count(), 'o dia 31 fica fora');
        $this->assertSame(1, Domain::query()->withStatus(Domain::STATUS_EXPIRED)->count());

        // needingAttention cobre vencidos e a vencer: tudo menos o dia 31.
        $this->assertSame(3, Domain::query()->needingAttention()->count());
    }

    public function test_the_renewal_counters_only_look_at_agency_managed_domains(): void
    {
        $this->domainExpiringIn(-3, Domain::MANAGED_BY_AGENCY);
        $this->domainExpiringIn(5, Domain::MANAGED_BY_AGENCY);
        $this->domainExpiringIn(-3, Domain::MANAGED_BY_CLIENT);
        $this->domainExpiringIn(5, Domain::MANAGED_BY_CLIENT);

        $this->get('/dominios')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('stats.total', 4)
                ->where('stats.expired', 1)
                ->where('stats.expiring', 1)
        );
    }

    public function test_the_dashboard_warns_about_agency_domains_only(): void
    {
        $this->domainExpiringIn(-2, Domain::MANAGED_BY_AGENCY);
        $this->domainExpiringIn(7, Domain::MANAGED_BY_AGENCY);
        $this->domainExpiringIn(-2, Domain::MANAGED_BY_CLIENT);
        $this->domainExpiringIn(400, Domain::MANAGED_BY_AGENCY);

        $this->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('domainAlerts.total', 2)
                ->has('domainAlerts.items', 2)
                // O mais urgente primeiro.
                ->where('domainAlerts.items.0.status', Domain::STATUS_EXPIRED)
                ->where('domainAlerts.items.1.status', Domain::STATUS_EXPIRING)
        );
    }

    public function test_the_dashboard_stays_quiet_when_nothing_needs_attention(): void
    {
        $this->domainExpiringIn(200, Domain::MANAGED_BY_AGENCY);
        $this->domainExpiringIn(-5, Domain::MANAGED_BY_CLIENT);

        $this->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page->where('domainAlerts.total', 0)->where('domainAlerts.items', [])
        );
    }

    public function test_the_dashboard_caps_the_list_but_keeps_the_full_count(): void
    {
        Domain::factory(9)->for($this->client)->managedByAgency()->expired()->create();

        $this->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page->where('domainAlerts.total', 9)->has('domainAlerts.items', 5)
        );
    }

    public function test_domains_without_a_date_never_raise_an_alert(): void
    {
        Domain::factory(3)->for($this->client)->managedByAgency()->withoutExpiration()->create();

        $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page->where('domainAlerts.total', 0));
    }
}
