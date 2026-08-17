<?php

namespace App\Models;

use Database\Factories\CostCenterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCenter extends Model
{
    /** @use HasFactory<CostCenterFactory> */
    use HasFactory;

    /** Paleta fechada, para os centros ficarem distinguíveis sem sair da identidade. */
    public const COLORS = ['blue', 'green', 'amber', 'red', 'sky', 'violet'];

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
