<?php

namespace App\Models;

use Database\Factories\FinanceCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCategory extends Model
{
    /** @use HasFactory<FinanceCategoryFactory> */
    use HasFactory;

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    /** @var list<string> */
    public const TYPES = [self::TYPE_INCOME, self::TYPE_EXPENSE];

    protected $fillable = ['name', 'type', 'color', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<FinanceCategory>  $query
     */
    public function scopeOfType(Builder $query, ?string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            return;
        }

        $query->where('type', $type);
    }
}
