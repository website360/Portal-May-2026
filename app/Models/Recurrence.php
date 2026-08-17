<?php

namespace App\Models;

use Database\Factories\RecurrenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Um compromisso que se repete: hospedagem anual, domínio, mensalidade de
 * cliente, licença de software.
 *
 * A recorrência não é um lançamento — ela os produz. Guardar só a regra e a data
 * do próximo vencimento evita encher o financeiro de contas de 2031 e permite
 * mudar o valor da renovação sem reescrever histórico.
 */
class Recurrence extends Model
{
    /** @use HasFactory<RecurrenceFactory> */
    use HasFactory;

    public const MONTHLY = 'monthly';

    public const QUARTERLY = 'quarterly';

    public const SEMIANNUAL = 'semiannual';

    public const ANNUAL = 'annual';

    /** @var list<string> */
    public const INTERVALS = [self::MONTHLY, self::QUARTERLY, self::SEMIANNUAL, self::ANNUAL];

    /** Quantos meses cada intervalo avança. */
    public const MONTHS = [
        self::MONTHLY => 1,
        self::QUARTERLY => 3,
        self::SEMIANNUAL => 6,
        self::ANNUAL => 12,
    ];

    protected $fillable = [
        'type', 'description', 'amount', 'interval', 'next_due_at', 'ends_at', 'active',
        'cost_center_id', 'finance_category_id', 'client_id', 'counterpart', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_due_at' => 'date',
            'ends_at' => 'date',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Já passou do fim? Derivado, não guardado: uma recorrência que terminou
     * ontem não precisa que ninguém rode nada para deixar de valer.
     */
    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->next_due_at->gt($this->ends_at);
    }

    /**
     * Está gerando lançamentos hoje?
     */
    public function isRunning(): bool
    {
        return $this->active && ! $this->hasEnded();
    }

    /**
     * Quantas cobranças ainda faltam, contando a próxima.
     *
     * Null quando não há fim marcado — é o contrato que corre até alguém
     * cancelar, e aí não existe "última" para avisar.
     */
    /**
     * Quantas cobranças o contrato tem no total, do começo ao fim.
     *
     * Null enquanto não há data de encerramento — contrato aberto não tem
     * "de doze" para numerar.
     */
    public function plannedTotal(): ?int
    {
        if ($this->ends_at === null) {
            return null;
        }

        return $this->transactions()->count() + max(0, $this->remaining() ?? 0);
    }

    public function remaining(): ?int
    {
        if ($this->ends_at === null) {
            return null;
        }

        if (! $this->active || $this->hasEnded()) {
            return 0;
        }

        $count = 0;
        $cursor = $this->next_due_at->copy();

        // O laço tem teto: uma data de fim absurda não pode travar a listagem.
        while ($cursor->lte($this->ends_at) && $count < 600) {
            $count++;
            $cursor = self::advance($cursor, $this->interval);
        }

        return $count;
    }

    /**
     * A próxima cobrança é a última do contrato.
     *
     * É este o aviso que interessa: sabendo em agosto que setembro fecha o
     * ciclo, dá tempo de renegociar antes de a cobrança simplesmente parar.
     */
    public function isLastCharge(): bool
    {
        return $this->remaining() === 1;
    }

    /**
     * Está chegando ao fim — por padrão, faltam duas cobranças ou menos.
     */
    public function isEnding(int $threshold = 2): bool
    {
        $remaining = $this->remaining();

        return $remaining !== null && $remaining > 0 && $remaining <= $threshold;
    }

    /**
     * Renova o contrato por mais N ciclos, empurrando a data de fim.
     *
     * Renovar não mexe no próximo vencimento nem no que já foi gerado: só
     * devolve fôlego ao contrato. O valor novo, quando muda, entra pelo
     * cadastro — e vale das próximas cobranças em diante, sem reescrever as
     * que já foram emitidas.
     */
    public function renew(int $cycles = 12, ?float $amount = null): void
    {
        $step = self::MONTHS[$this->interval];

        /*
         * `cycles` são cobranças, não meses de calendário.
         *
         * Com o contrato ainda correndo, cada intervalo somado ao fim acrescenta
         * uma cobrança. Com o contrato já vencido, a contagem recomeça do próximo
         * vencimento — e aí a primeira das N é ele mesmo, daí o menos um. Sem essa
         * distinção, pedir "mais duas" entregava três.
         */
        $ended = $this->ends_at === null || $this->ends_at->lt($this->next_due_at);

        $endsAt = $ended
            ? $this->next_due_at->copy()->addMonthsNoOverflow($step * ($cycles - 1))
            : $this->ends_at->copy()->addMonthsNoOverflow($step * $cycles);

        $this->update([
            'ends_at' => $endsAt,
            'active' => true,
            ...($amount !== null ? ['amount' => $amount] : []),
        ]);
    }

    /**
     * Recorrências perto do fim, para o painel avisar.
     *
     * O filtro grosso vai no banco (tem fim marcado, ativa, ainda não acabou) e
     * a contagem exata fica no PHP: quantas cobranças cabem até o fim depende do
     * intervalo, e SQL portátil para isso sairia bem mais caro de manter do que
     * vale para uma tabela desse tamanho.
     *
     * @param  Builder<Recurrence>  $query
     */
    public function scopeEnding(Builder $query): void
    {
        $query->where('active', true)
            ->whereNotNull('ends_at')
            ->whereColumn('next_due_at', '<=', 'ends_at');
    }

    /**
     * Cria o lançamento do vencimento atual e avança para o próximo.
     *
     * Devolve null quando não havia o que gerar — recorrência parada, encerrada,
     * ou o lançamento daquele vencimento já existente. Chamar duas vezes no
     * mesmo dia não duplica nada.
     */
    public function generateNext(?int $number = null, ?int $total = null): ?Transaction
    {
        if (! $this->isRunning()) {
            return null;
        }

        $dueDate = $this->next_due_at->copy();

        $existing = $this->transactions()->whereDate('due_date', $dueDate)->first();

        if ($existing === null) {
            $transaction = $this->transactions()->create([
                'type' => $this->type,
                'description' => $this->description,
                'amount' => $this->amount,
                'due_date' => $dueDate,
                'cost_center_id' => $this->cost_center_id,
                'finance_category_id' => $this->finance_category_id,
                'client_id' => $this->client_id,
                'counterpart' => $this->counterpart,
                'notes' => $this->notes,
                // Reaproveita as colunas de parcela para numerar a cobrança
                // dentro do contrato: é o "02/12" que a listagem mostra.
                'installment' => $number ?? ($this->transactions()->count() + 1),
                'installments' => $total ?? $this->plannedTotal(),
            ]);
        } else {
            $transaction = null;
        }

        $this->update(['next_due_at' => self::advance($dueDate, $this->interval)]);

        return $transaction;
    }

    /**
     * Avança uma data pelo intervalo, sem escorregar de mês.
     *
     * `addMonths` no Carbon leva 31/01 para 03/03, porque fevereiro não tem 31.
     * Numa conta que vence todo dia 31 isso mudaria o mês de cobrança — daí o
     * `addMonthsNoOverflow`, que devolve o último dia do mês de destino.
     */
    public static function advance(Carbon $date, string $interval): Carbon
    {
        return $date->copy()->addMonthsNoOverflow(self::MONTHS[$interval] ?? 12);
    }

    /**
     * Vencimentos que cairiam dentro do intervalo, ainda não gerados.
     *
     * São projeções, não lançamentos: servem para a pessoa enxergar o que vem
     * pela frente sem que o financeiro nasça cheio de contas de 2031 — e sem
     * travar o valor, que ainda pode mudar até lá.
     *
     * @return list<Carbon>
     */
    public function occurrencesBetween(Carbon $start, Carbon $end): array
    {
        if (! $this->isRunning()) {
            return [];
        }

        $dates = [];
        $cursor = $this->next_due_at->copy();
        $limit = $this->ends_at;

        // Teto: uma data de fim absurda não pode fazer a tela girar sem parar.
        for ($i = 0; $i < 600 && $cursor->lte($end); $i++) {
            if ($limit !== null && $cursor->gt($limit)) {
                break;
            }

            if ($cursor->gte($start)) {
                $dates[] = $cursor->copy();
            }

            $cursor = self::advance($cursor, $this->interval);
        }

        return $dates;
    }

    /**
     * Recorrências que já deveriam ter virado lançamento.
     *
     * @param  Builder<Recurrence>  $query
     */
    public function scopeDue(Builder $query, Carbon $until): void
    {
        $query->where('active', true)
            ->whereDate('next_due_at', '<=', $until)
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhereColumn('next_due_at', '<=', 'ends_at'));
    }
}
