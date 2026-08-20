<?php

namespace App\Models;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Um contrato de um cliente: os dados, o texto gerado e o PDF.
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /** Dentro dessa janela o contrato entra na lista de renovação. */
    public const EXPIRING_WINDOW_DAYS = 30;

    /** Dentro dessa janela o reajuste de preço entra na lista de "a reajustar". */
    public const REVIEW_WINDOW_DAYS = 30;

    /** Período contratado: o ciclo mensal ou anual. */
    public const BILLING_MONTHLY = 'monthly';

    public const BILLING_ANNUAL = 'annual';

    public const BILLING_PERIODS = [self::BILLING_MONTHLY, self::BILLING_ANNUAL];

    /** Meses que cada período contratado avança — guia a renovação. */
    public const BILLING_MONTHS = [self::BILLING_MONTHLY => 1, self::BILLING_ANNUAL => 12];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRING = 'expiring';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_id', 'contract_template_id', 'number', 'title', 'service',
        'value', 'starts_at', 'ends_at', 'body', 'variables', 'notes', 'signed_at',
        // Sem isto o cancelamento era gravado em silêncio e nada acontecia.
        'cancelled_at', 'pdf_path', 'active_without_signature',
        'billing_period', 'price_review_at', 'price_review_years',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'signed_at' => 'date',
            'cancelled_at' => 'datetime',
            'variables' => 'array',
            'active_without_signature' => 'boolean',
            'price_review_at' => 'date',
            'price_review_years' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Contract $contract): void {
            // Sem título, o serviço serve de título — o formulário não pede mais.
            if (blank($contract->title)) {
                $contract->title = $contract->service;
            }

            // A data de reajuste é calculada, não digitada: início + a cadência (padrão 2 anos).
            if ($contract->starts_at !== null) {
                $contract->price_review_at = $contract->starts_at->copy()->addYears((int) ($contract->price_review_years ?: 2));
            }
        });
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<ContractTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    /**
     * Numeração sequencial por ano: MAY-2026-0001.
     *
     * Conta os do ano, e não o id, para a sequência ser legível e recomeçar
     * a cada ano — que é como se numera contrato.
     */
    public static function nextNumber(?Carbon $date = null): string
    {
        $year = ($date ?? Carbon::today())->year;
        $prefix = config('contratos.prefixo');

        $last = self::query()
            ->where('number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }

    /** Dias até o fim da vigência. Null quando o prazo é indeterminado. */
    public function daysLeft(): ?int
    {
        if ($this->ends_at === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->ends_at->startOfDay(), false);
    }

    /** Dias até o próximo reajuste de preço. Null quando não há reajuste marcado. */
    public function daysToReview(): ?int
    {
        if ($this->price_review_at === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->price_review_at->startOfDay(), false);
    }

    /**
     * O reajuste está na hora: dentro da janela de aviso ou já vencido — mas só
     * para contratos que ainda vigoram (não cancelados).
     */
    public function reviewDue(): bool
    {
        $days = $this->daysToReview();

        return $days !== null && $days <= self::REVIEW_WINDOW_DAYS && $this->cancelled_at === null;
    }

    public function status(): string
    {
        if ($this->cancelled_at !== null) {
            return self::STATUS_CANCELLED;
        }

        // Sem assinatura é rascunho, mesmo dentro da vigência: o que vale para
        // as duas partes é o papel assinado, não a data digitada aqui.
        //
        // Exceção: contratos cadastrados direto (sem gerar documento) valem pela
        // data — não há papel a assinar, então a flag os tira do rascunho.
        if ($this->signed_at === null && ! $this->active_without_signature) {
            return self::STATUS_DRAFT;
        }

        $days = $this->daysLeft();

        return match (true) {
            $days === null => self::STATUS_ACTIVE,
            $days < 0 => self::STATUS_ENDED,
            $days <= self::EXPIRING_WINDOW_DAYS => self::STATUS_EXPIRING,
            default => self::STATUS_ACTIVE,
        };
    }

    public function pdfUrl(): ?string
    {
        return $this->pdf_path ? Storage::disk('public')->url($this->pdf_path) : null;
    }

    /** O PDF anexado é o assinado; o gerado sai do texto na hora do download. */
    public function hasAttachment(): bool
    {
        return filled($this->pdf_path);
    }

    /**
     * O mesmo critério de `status()`, em SQL.
     *
     * @param  Builder<Contract>  $query
     * @param  list<string>  $statuses
     */
    public function scopeWithStatuses(Builder $query, array $statuses): void
    {
        $statuses = array_values(array_intersect($statuses, [
            self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_EXPIRING, self::STATUS_ENDED, self::STATUS_CANCELLED,
        ]));

        if ($statuses === []) {
            return;
        }

        $today = Carbon::today()->toDateString();
        // Limite exclusivo: a coluna `date` vira "AAAA-MM-DD 00:00:00" e, como
        // texto, o último dia da janela ficaria de fora de um `<=`.
        $limit = Carbon::today()->addDays(self::EXPIRING_WINDOW_DAYS + 1)->toDateString();

        $query->where(function (Builder $query) use ($statuses, $today, $limit) {
            foreach ($statuses as $status) {
                $query->orWhere(function (Builder $query) use ($status, $today, $limit) {
                    if ($status === self::STATUS_CANCELLED) {
                        $query->whereNotNull('cancelled_at');

                        return;
                    }

                    $query->whereNull('cancelled_at');

                    if ($status === self::STATUS_DRAFT) {
                        $query->whereNull('signed_at');

                        return;
                    }

                    $query->whereNotNull('signed_at');

                    match ($status) {
                        self::STATUS_ENDED => $query->whereNotNull('ends_at')->where('ends_at', '<', $today),
                        self::STATUS_EXPIRING => $query->whereNotNull('ends_at')
                            ->where('ends_at', '>=', $today)
                            ->where('ends_at', '<', $limit),
                        self::STATUS_ACTIVE => $query->where(fn (Builder $q) => $q
                            ->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $limit)),
                    };
                });
            }
        });
    }

    /**
     * @param  Builder<Contract>  $query
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
     * @param  Builder<Contract>  $query
     * @param  list<string>  $services
     */
    public function scopeOfServices(Builder $query, array $services): void
    {
        if ($services === []) {
            return;
        }

        $query->whereIn('service', $services);
    }

    /**
     * @param  Builder<Contract>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->where('number', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('service', 'like', "%{$term}%")
                ->orWhereHas('client', fn (Builder $client) => $client
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('trade_name', 'like', "%{$term}%"));
        });
    }
}
