<?php

namespace App\Models;

use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * O contrato de manutenção de um site.
 *
 * A manutenção é mensal e o dia dentro do mês é livre, então não existe data de
 * vencimento: a pergunta é sempre "este mês já teve a dele?". Tudo — situação,
 * atraso, filtro — deriva de qual foi o último mês atendido.
 */
class MaintenancePlan extends Model
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use HasFactory;

    /** Já teve manutenção no mês corrente. */
    public const STATUS_DONE = 'done';

    /** O mês corrente ainda não teve a dele — mas ainda dá tempo. */
    public const STATUS_PENDING = 'pending';

    /** Passou um mês inteiro sem manutenção. */
    public const STATUS_LATE = 'late';

    public const STATUS_PAUSED = 'paused';

    protected $fillable = ['client_id', 'site_url', 'active', 'notes'];

    protected function casts(): array
    {
        return [
            'last_performed_at' => 'date',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Maintenance, $this> */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    /**
     * Relê as manutenções e regrava a última.
     *
     * Chamado pelos eventos de Maintenance: registrar, corrigir a data ou
     * excluir uma manutenção muda qual foi o último mês atendido.
     */
    public function refreshSchedule(): void
    {
        $this->last_performed_at = $this->maintenances()->max('performed_at');

        $this->saveQuietly();
    }

    /**
     * Meses que estão devendo manutenção, contando o corrente.
     *
     * Zero quando o mês já foi atendido; um quando é o mês corrente que falta;
     * dois ou mais quando algum mês passou em branco.
     */
    public function pendingMonths(): int
    {
        /*
         * Nunca atendido conta a partir do mês anterior ao cadastro: assim um
         * plano criado hoje já nasce devendo o mês corrente — que é a verdade —
         * sem precisar de uma data de início digitada à mão.
         */
        $reference = $this->last_performed_at
            ? $this->last_performed_at->copy()->startOfMonth()
            : ($this->created_at ?? Carbon::now())->copy()->startOfMonth()->subMonthNoOverflow();

        return max(0, (int) $reference->diffInMonths(Carbon::today()->startOfMonth()));
    }

    public function status(): string
    {
        if (! $this->active) {
            return self::STATUS_PAUSED;
        }

        return match ($this->pendingMonths()) {
            0 => self::STATUS_DONE,
            1 => self::STATUS_PENDING,
            default => self::STATUS_LATE,
        };
    }

    /**
     * O mesmo critério de `status()`, em SQL.
     *
     * Aceita vários status de uma vez — os filtros do sistema são múltiplos.
     * Pausado é excludente: não é um ponto do calendário, e sim a ausência dele,
     * por isso entra como um OR à parte.
     *
     * @param  Builder<MaintenancePlan>  $query
     * @param  list<string>  $statuses
     */
    public function scopeWithStatuses(Builder $query, array $statuses): void
    {
        $statuses = array_values(array_intersect($statuses, [
            self::STATUS_DONE, self::STATUS_PENDING, self::STATUS_LATE, self::STATUS_PAUSED,
        ]));

        if ($statuses === []) {
            return;
        }

        $thisMonth = Carbon::today()->startOfMonth()->toDateString();
        $lastMonth = Carbon::today()->startOfMonth()->subMonthNoOverflow()->toDateString();

        $query->where(function (Builder $query) use ($statuses, $thisMonth, $lastMonth) {
            foreach ($statuses as $status) {
                $query->orWhere(function (Builder $query) use ($status, $thisMonth, $lastMonth) {
                    if ($status === self::STATUS_PAUSED) {
                        $query->where('active', false);

                        return;
                    }

                    $query->where('active', true);

                    match ($status) {
                        self::STATUS_DONE => $query->where('last_performed_at', '>=', $thisMonth),

                        // Atendido no mês passado, ou cadastrado neste mês e
                        // ainda sem nenhuma — os dois devem só o mês corrente.
                        self::STATUS_PENDING => $query->where(fn (Builder $q) => $q
                            ->where(fn (Builder $q) => $q
                                ->where('last_performed_at', '>=', $lastMonth)
                                ->where('last_performed_at', '<', $thisMonth))
                            ->orWhere(fn (Builder $q) => $q
                                ->whereNull('last_performed_at')
                                ->where('created_at', '>=', $thisMonth))),

                        self::STATUS_LATE => $query->where(fn (Builder $q) => $q
                            ->where('last_performed_at', '<', $lastMonth)
                            ->orWhere(fn (Builder $q) => $q
                                ->whereNull('last_performed_at')
                                ->where('created_at', '<', $thisMonth))),
                    };
                });
            }
        });
    }

    /**
     * Do mais urgente para o menos: nunca atendido primeiro, depois o que está
     * parado há mais tempo.
     *
     * @param  Builder<MaintenancePlan>  $query
     */
    public function scopeMostUrgent(Builder $query, string $direction = 'asc'): void
    {
        $query->orderByRaw('last_performed_at is not null')->orderBy('last_performed_at', $direction);
    }

    /**
     * @param  Builder<MaintenancePlan>  $query
     * @param  list<string>  $clients
     */
    public function scopeOfClients(Builder $query, array $clients): void
    {
        if ($clients === []) {
            return;
        }

        $query->whereIn('client_id', $clients);
    }

    /**
     * @param  Builder<MaintenancePlan>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->where('site_url', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $client) => $client
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('trade_name', 'like', "%{$term}%"));
        });
    }
}
