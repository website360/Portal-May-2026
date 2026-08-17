<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const TYPE_PAYABLE = 'payable';

    public const TYPE_RECEIVABLE = 'receivable';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_PENDING = 'pending';

    /** Recorte, não situação: tudo que ainda não foi baixado (a vencer + vencido). */
    public const STATUS_OPEN = 'open';

    /** @var list<string> */
    public const TYPES = [self::TYPE_PAYABLE, self::TYPE_RECEIVABLE];

    /** @var list<string> */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_OVERDUE, self::STATUS_PAID];

    protected $fillable = [
        'type', 'description', 'amount', 'due_date', 'paid_at', 'paid_amount',
        'cost_center_id', 'finance_category_id', 'client_id',
        'counterpart', 'supplier_id', 'payment_method', 'payment_method_id', 'notes',
        'series_id', 'installment', 'installments', 'recurrence_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'date',
        ];
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return BelongsTo<FinanceCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /** Alcance de uma edição ou exclusão dentro de uma série. */
    public const SCOPE_ONE = 'one';

    public const SCOPE_FORWARD = 'forward';

    public const SCOPE_ALL = 'all';

    /** @var list<string> */
    public const SCOPES = [self::SCOPE_ONE, self::SCOPE_FORWARD, self::SCOPE_ALL];

    /** Pertence a um parcelamento ou a um contrato? */
    public function belongsToSeries(): bool
    {
        return $this->recurrence_id !== null || $this->series_id !== null;
    }

    /**
     * As contas que a ação alcança.
     *
     * `one` é sempre esta. `forward` pega desta em diante, pelo vencimento —
     * o que já foi pago no passado fica intacto. `all` pega a série inteira.
     *
     * Fora de série, qualquer escopo resolve para a própria conta: assim quem
     * chama não precisa checar antes.
     *
     * @return Builder<Transaction>
     */
    public function inScope(string $scope): Builder
    {
        $column = match (true) {
            $this->recurrence_id !== null => 'recurrence_id',
            $this->series_id !== null => 'series_id',
            default => null,
        };

        if ($column === null || $scope === self::SCOPE_ONE || ! in_array($scope, self::SCOPES, true)) {
            return static::query()->whereKey($this->id);
        }

        $query = static::query()->where($column, $this->{$column});

        if ($scope === self::SCOPE_FORWARD) {
            $query->where('due_date', '>=', $this->due_date->toDateString());
        }

        return $query;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * Situação derivada da data de pagamento e do vencimento — nunca gravada,
     * senão "vencida" precisaria de alguém rodando todo dia para atualizar.
     */
    public function status(): string
    {
        if ($this->isPaid()) {
            return self::STATUS_PAID;
        }

        return $this->due_date->startOfDay()->isBefore(Carbon::today())
            ? self::STATUS_OVERDUE
            : self::STATUS_PENDING;
    }

    public function daysLeft(): int
    {
        return (int) Carbon::today()->diffInDays($this->due_date->startOfDay(), false);
    }

    /** Quanto entrou ou saiu de fato; sem baixa, o previsto. */
    public function settledAmount(): float
    {
        return (float) ($this->paid_amount ?? $this->amount);
    }

    /**
     * Dar e desfazer baixa. O valor pago acompanha, para que uma conta quitada
     * com juros ou desconto não minta no relatório.
     */
    public function settle(?Carbon $paidAt = null, ?float $paidAmount = null): void
    {
        $this->paid_at = $paidAt ?? Carbon::today();
        $this->paid_amount = $paidAmount ?? (float) $this->amount;

        $this->save();
    }

    public function reopen(): void
    {
        $this->paid_at = null;
        $this->paid_amount = null;

        $this->save();
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public function scopeOfType(Builder $query, ?string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            return;
        }

        $query->where('type', $type);
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  list<string>  $types
     */
    public function scopeOfTypes(Builder $query, array $types): void
    {
        $valid = array_values(array_intersect($types, self::TYPES));

        if ($valid !== []) {
            $query->whereIn('type', $valid);
        }
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('paid_at');
    }

    /**
     * Mesmo critério de `status()`, só que em SQL.
     *
     * @param  Builder<Transaction>  $query
     */
    /**
     * Vários recortes de uma vez — "em aberto" e "vencida" juntas, por exemplo.
     *
     * Cada situação é uma condição própria; combiná-las com AND devolveria
     * sempre vazio, já que nenhuma conta é paga e vencida ao mesmo tempo. Daí o
     * OR dentro de um grupo, para não vazar sobre os outros filtros.
     *
     * @param  Builder<Transaction>  $query
     * @param  list<string>  $statuses
     */
    public function scopeWithStatuses(Builder $query, array $statuses): void
    {
        $valid = array_values(array_intersect($statuses, [...self::STATUSES, self::STATUS_OPEN]));

        if ($valid === []) {
            return;
        }

        $query->where(function (Builder $group) use ($valid) {
            foreach ($valid as $status) {
                $group->orWhere(fn (Builder $one) => $one->withStatus($status));
            }
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): void
    {
        $today = Carbon::today()->toDateString();

        match ($status) {
            self::STATUS_PAID => $query->whereNotNull('paid_at'),
            self::STATUS_OVERDUE => $query->whereNull('paid_at')->where('due_date', '<', $today),
            self::STATUS_PENDING => $query->whereNull('paid_at')->where('due_date', '>=', $today),
            self::STATUS_OPEN => $query->whereNull('paid_at'),
            default => null,
        };
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->withStatus(self::STATUS_OVERDUE);
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public function scopeInMonth(Builder $query, ?string $month): void
    {
        if (blank($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return;
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();

        /*
         * Intervalo semiaberto [início do mês, início do próximo). Um
         * `whereBetween` até o último dia perderia esse dia: a coluna `date`
         * é gravada como "2026-03-31 00:00:00", que comparado como texto fica
         * maior que "2026-03-31".
         */
        $query->where('due_date', '>=', $start->toDateString())
            ->where('due_date', '<', $start->copy()->addMonth()->toDateString());
    }

    /**
     * Baixado dentro do mês — quando o dinheiro andou de verdade, e não quando
     * era esperado. É o que sustenta "Recebido" e "Pago" nos indicadores.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeSettledInMonth(Builder $query, ?string $month): void
    {
        if (blank($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();

        $query->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start->toDateString())
            ->where('paid_at', '<', $start->copy()->addMonth()->toDateString());
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->where('description', 'like', "%{$term}%")
                ->orWhere('counterpart', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * Em aberto primeiro, do vencimento mais antigo para o mais novo — a ordem
     * em que as contas exigem ação.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeInDueOrder(Builder $query): void
    {
        $query->orderByRaw('paid_at is not null')
            ->orderBy('due_date')
            ->orderByDesc('id');
    }
}
