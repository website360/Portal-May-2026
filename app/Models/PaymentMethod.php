<?php

namespace App\Models;

use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Como o dinheiro entra ou sai: Pix, boleto, cartão, transferência.
 *
 * Vira cadastro em vez de texto livre porque é o que permite responder "quanto
 * passou no cartão este mês" sem depender de todo mundo escrever igual.
 */
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    /** Mesma paleta dos centros de custo, para o financeiro ficar coerente. */
    public const COLORS = CostCenter::COLORS;

    protected $fillable = ['name', 'description', 'color', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
