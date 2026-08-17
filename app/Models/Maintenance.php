<?php

namespace App\Models;

use App\Support\MaintenanceChecklist;
use Database\Factories\MaintenanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma manutenção executada: o checklist preenchido daquele dia.
 */
class Maintenance extends Model
{
    /** @use HasFactory<MaintenanceFactory> */
    use HasFactory;

    protected $fillable = [
        'maintenance_plan_id', 'user_id', 'performed_at', 'items', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'items' => 'array',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Registrar, corrigir a data ou apagar uma manutenção move a próxima do
        // plano. Fica aqui para não depender de quem chamou lembrar disso.
        static::saved(fn (self $maintenance) => $maintenance->plan->refreshSchedule());
        static::deleted(fn (self $maintenance) => $maintenance->plan->refreshSchedule());
    }

    /** @return BelongsTo<MaintenancePlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function doneCount(): int
    {
        return MaintenanceChecklist::countOf($this->items ?? [], MaintenanceChecklist::DONE);
    }

    public function skippedCount(): int
    {
        return MaintenanceChecklist::countOf($this->items ?? [], MaintenanceChecklist::SKIPPED);
    }

    /**
     * @param  Builder<Maintenance>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->whereHas('plan', fn (Builder $plan) => $plan->search($term));
    }

    /**
     * @param  Builder<Maintenance>  $query
     * @param  list<string>  $clients
     */
    public function scopeOfClients(Builder $query, array $clients): void
    {
        if ($clients === []) {
            return;
        }

        $query->whereHas('plan', fn (Builder $plan) => $plan->whereIn('client_id', $clients));
    }

    /**
     * @param  Builder<Maintenance>  $query
     * @param  list<string>  $users
     */
    public function scopeByUsers(Builder $query, array $users): void
    {
        if ($users === []) {
            return;
        }

        $query->whereIn('user_id', $users);
    }

    /**
     * Estado do relatório: enviado ou não.
     *
     * O "não enviado" é o recorte que importa — é dele que sai a lista do que
     * ainda precisa ser reenviado.
     *
     * @param  Builder<Maintenance>  $query
     * @param  list<string>  $reports
     */
    public function scopeWithReports(Builder $query, array $reports): void
    {
        $reports = array_values(array_intersect($reports, ['sent', 'not_sent']));

        // Os dois marcados é o mesmo que nenhum: não sobra nada de fora.
        if (count($reports) !== 1) {
            return;
        }

        $reports[0] === 'sent'
            ? $query->whereNotNull('whatsapp_sent_at')
            : $query->whereNull('whatsapp_sent_at');
    }

    /**
     * Manutenções de um mês, no formato "AAAA-MM".
     *
     * Intervalo semiaberto: a coluna `date` vira "AAAA-MM-DD 00:00:00" e, como
     * texto, o último dia do mês ficaria de fora de um `<=`.
     *
     * @param  Builder<Maintenance>  $query
     */
    public function scopeInMonth(Builder $query, ?string $month): void
    {
        if (! is_string($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return;
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();

        $query->where('performed_at', '>=', $start->toDateString())
            ->where('performed_at', '<', $start->copy()->addMonthNoOverflow()->toDateString());
    }
}
