<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_COMPANY = 'company';

    public const TYPE_PERSON = 'person';

    protected $fillable = [
        'type', 'name', 'trade_name', 'document', 'photo_path', 'status',
        'email', 'phone', 'contact_name', 'contact_role',
        'representative_name', 'representative_role', 'representative_document',
        'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state',
        'segment', 'monthly_fee', 'started_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
            'started_at' => 'date',
        ];
    }

    /**
     * URL publica da foto, ou null quando o cliente nao tem uma.
     *
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null);
    }

    /**
     * Como a agência chama o cliente no dia a dia.
     *
     * A marca vem antes da razão social e do nome civil: ninguém aqui reconhece
     * "Adriana Maria dos Santos Veigas", mas todo mundo sabe quem é "Inove-se".
     * Vale também para pessoa física que atende por marca própria.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->trade_name) ? $this->trade_name : $this->name);
    }

    /**
     * Lista para os seletores de cliente do sistema inteiro.
     *
     * Ordena e rotula pela marca, não pela razão social — procurar por "Inove-se"
     * é o que a pessoa faz, e é assim que a lista fica na ordem que ela espera.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public static function pickList(): Collection
    {
        return self::query()
            ->orderByRaw('coalesce(nullif(trade_name, ""), name)')
            ->get(['id', 'name', 'trade_name', 'document'])
            ->map(fn (self $client) => [
                'id' => $client->id,
                'name' => $client->display_name,
                /*
                 * Termos que também encontram o cliente sem aparecer na linha:
                 * a razão social, quando o rótulo é a marca, e o documento.
                 * Quem lembra "Adriana" ou digita o CNPJ acha "Inove-se".
                 */
                'search' => trim(implode(' ', array_filter([$client->name, $client->trade_name, $client->document]))),
            ]);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Domain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Busca por nome, nome fantasia, e-mail ou documento.
     *
     * @param  Builder<Client>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            foreach (['name', 'trade_name', 'email', 'document'] as $column) {
                $query->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    /**
     * @param  Builder<Client>  $query
     */
    public function scopeStatus(Builder $query, ?string $status): void
    {
        if (! in_array($status, [self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
            return;
        }

        $query->where('status', $status);
    }

    /**
     * @param  Builder<Client>  $query
     */
    public function scopeType(Builder $query, ?string $type): void
    {
        if (! in_array($type, [self::TYPE_COMPANY, self::TYPE_PERSON], true)) {
            return;
        }

        $query->where('type', $type);
    }
}
