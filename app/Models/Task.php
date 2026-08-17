<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DOING = 'doing';

    public const STATUS_DONE = 'done';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_DOING, self::STATUS_DONE];

    /** @var list<string> */
    public const PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT];

    protected $fillable = [
        'project_id', 'client_id', 'user_id',
        'title', 'description', 'status', 'priority', 'due_date', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Responsável pela tarefa. @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    /** Atrasada só faz sentido para o que ainda não foi concluído. */
    public function isOverdue(): bool
    {
        return ! $this->isDone()
            && $this->due_date !== null
            && $this->due_date->startOfDay()->isBefore(Carbon::today());
    }

    /**
     * Concluir carimba a data; reabrir apaga. Assim o "concluídas hoje" tem de
     * onde sair sem uma tabela de histórico.
     */
    public function moveTo(string $status): void
    {
        $this->status = $status;
        $this->completed_at = $status === self::STATUS_DONE ? ($this->completed_at ?? now()) : null;

        $this->save();
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $client) => $client->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeWithStatus(Builder $query, ?string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            return;
        }

        $query->where('status', $status);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_DONE);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('due_date')->where('due_date', '<', Carbon::today()->toDateString());
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeCompletedToday(Builder $query): void
    {
        $query->where('status', self::STATUS_DONE)
            ->whereBetween('completed_at', [Carbon::today(), Carbon::today()->endOfDay()]);
    }

    /**
     * Ordem de trabalho: aberto antes de concluído, urgência antes de folga,
     * prazo mais próximo primeiro e sem prazo por último.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeInWorkOrder(Builder $query): void
    {
        $query->orderByRaw("case status when 'doing' then 0 when 'pending' then 1 else 2 end")
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderByDesc('id');
    }
}
