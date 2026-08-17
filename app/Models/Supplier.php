<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Quem a agência paga: hospedagem, contador, fornecedor de material.
 *
 * Vira cadastro em vez de texto livre pelo mesmo motivo das formas de pagamento
 * — é o que permite responder "quanto paguei para a Locaweb este ano" sem
 * depender de todo mundo escrever o nome igual.
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = ['name', 'trade_name', 'document', 'email', 'phone', 'notes', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** A marca antes da razão social, como nos clientes. */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->trade_name) ? $this->trade_name : $this->name);
    }

    /**
     * @return Collection<int, array{id: int, name: string, search: string}>
     */
    public static function pickList(): Collection
    {
        return self::query()
            ->where('active', true)
            ->orderByRaw('coalesce(nullif(trade_name, ""), name)')
            ->get(['id', 'name', 'trade_name', 'document'])
            ->map(fn (self $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->display_name,
                'search' => trim(implode(' ', array_filter([$supplier->name, $supplier->trade_name, $supplier->document]))),
            ]);
    }
}
