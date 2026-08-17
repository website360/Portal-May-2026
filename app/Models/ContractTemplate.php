<?php

namespace App\Models;

use App\Support\ContractPlaceholders;
use Database\Factories\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O modelo de contrato de um serviço.
 *
 * É o modelo que define o contrato: escolher o serviço na tela de geração é
 * escolher qual texto sai. Criar um serviço novo é escrever um modelo novo,
 * sem passar por código.
 */
class ContractTemplate extends Model
{
    /** @use HasFactory<ContractTemplateFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'body', 'active', 'with_signatures'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'with_signatures' => 'boolean'];
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Os marcadores que este modelo pede e o sistema não sabe responder.
     *
     * @return list<string>
     */
    public function customPlaceholders(): array
    {
        return ContractPlaceholders::customIn($this->body);
    }
}
