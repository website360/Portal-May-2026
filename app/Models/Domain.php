<?php

namespace App\Models;

use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory;

    public const MANAGED_BY_AGENCY = 'agency';

    public const MANAGED_BY_CLIENT = 'client';

    /** Janela de aviso: dentro dela, o dominio entra na lista de renovacao. */
    public const EXPIRING_WINDOW_DAYS = 30;

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_EXPIRING = 'expiring';

    public const STATUS_OK = 'ok';

    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'client_id', 'name', 'registrar', 'managed_by',
        'registered_at', 'expires_at', 'auto_renew', 'annual_cost', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date',
            'expires_at' => 'date',
            'auto_renew' => 'boolean',
            'annual_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Dias até vencer. Negativo quando já venceu, null quando não há data.
     */
    public function daysLeft(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->expires_at->startOfDay(), false);
    }

    public function status(): string
    {
        $days = $this->daysLeft();

        return match (true) {
            $days === null => self::STATUS_UNKNOWN,
            $days < 0 => self::STATUS_EXPIRED,
            $days <= self::EXPIRING_WINDOW_DAYS => self::STATUS_EXPIRING,
            default => self::STATUS_OK,
        };
    }

    public function managedByAgency(): bool
    {
        return $this->managed_by === self::MANAGED_BY_AGENCY;
    }

    /**
     * @param  Builder<Domain>  $query
     */
    public function scopeManagedBy(Builder $query, ?string $managedBy): void
    {
        if (! in_array($managedBy, [self::MANAGED_BY_AGENCY, self::MANAGED_BY_CLIENT], true)) {
            return;
        }

        $query->where('managed_by', $managedBy);
    }

    /**
     * Filtra pelo mesmo critério que `status()` calcula, só que em SQL. As datas
     * vão como string 'Y-m-d' para o MySQL e o SQLite compararem igual.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeWithStatus(Builder $query, ?string $status): void
    {
        $today = Carbon::today()->toDateString();
        // Limite exclusivo: o dia seguinte ao último da janela. Comparar com o
        // próprio último dia perderia esse dia, porque a coluna `date` é gravada
        // como "AAAA-MM-DD 00:00:00" e, como texto, isso é maior que "AAAA-MM-DD".
        $limit = Carbon::today()->addDays(self::EXPIRING_WINDOW_DAYS + 1)->toDateString();

        match ($status) {
            self::STATUS_EXPIRED => $query->whereNotNull('expires_at')->where('expires_at', '<', $today),
            self::STATUS_EXPIRING => $query->where('expires_at', '>=', $today)->where('expires_at', '<', $limit),
            self::STATUS_OK => $query->where('expires_at', '>=', $limit),
            self::STATUS_UNKNOWN => $query->whereNull('expires_at'),
            default => null,
        };
    }

    /**
     * Vencidos e a vencer, do mais urgente para o menos.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeNeedingAttention(Builder $query): void
    {
        $limit = Carbon::today()->addDays(self::EXPIRING_WINDOW_DAYS + 1)->toDateString();

        $query->whereNotNull('expires_at')
            ->where('expires_at', '<', $limit)
            ->orderBy('expires_at');
    }

    /**
     * @param  Builder<Domain>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('registrar', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$term}%"));
        });
    }
}
