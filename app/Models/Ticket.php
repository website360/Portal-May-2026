<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_DOING = 'doing';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    /** A ordem também é a das colunas do quadro. */
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_DOING, self::STATUS_RESOLVED, self::STATUS_CLOSED];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT];

    /** Prioridade por extenso, para textos que uma pessoa lê (avisos, e-mail). */
    public const PRIORITY_LABELS = [
        self::PRIORITY_LOW => 'Baixa',
        self::PRIORITY_NORMAL => 'Normal',
        self::PRIORITY_HIGH => 'Alta',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    /** De onde o ticket entrou. */
    public const CHANNELS = ['manual', 'whatsapp', 'email'];

    protected $fillable = [
        'number', 'subject', 'client_id', 'assignee_id', 'opened_by',
        'status', 'priority', 'category', 'channel',
        'first_response_at', 'last_reply_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'first_response_at' => 'datetime',
            'last_reply_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);
    }

    /** Próximo número sequencial, tipo T0001. */
    public static function nextNumber(): string
    {
        return 'T'.str_pad((string) ((int) static::query()->max('id') + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Move para um status, carimbando fechamento e primeira resposta quando cabe. */
    public function moveTo(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            return;
        }

        $this->status = $status;
        $this->closed_at = $this->isClosed() ? ($this->closed_at ?? now()) : null;
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (! $term) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('subject', 'like', "%{$term}%")
                ->orWhere('number', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', "%{$term}%")->orWhere('trade_name', 'like', "%{$term}%"));
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): void
    {
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
    }

    public function scopeOfPriority(Builder $query, ?string $priority): void
    {
        if (in_array($priority, self::PRIORITIES, true)) {
            $query->where('priority', $priority);
        }
    }

    public function scopeOfAssignee(Builder $query, ?string $assignee): void
    {
        if ($assignee === 'unassigned') {
            $query->whereNull('assignee_id');
        } elseif (is_numeric($assignee)) {
            $query->where('assignee_id', (int) $assignee);
        }
    }

    public function scopeOfClient(Builder $query, ?string $clientId): void
    {
        if (is_numeric($clientId)) {
            $query->where('client_id', (int) $clientId);
        }
    }
}
