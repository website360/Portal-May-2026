<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Administrador passa por cima de qualquer permissão, inclusive a de usuários. */
    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'photo_path',
        'role',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'photo_path',
    ];

    /**
     * O front consome sempre `avatar`, nunca o caminho no disco. Anexar aqui faz
     * a foto acompanhar o usuário em toda serialização — inclusive o `auth.user`
     * compartilhado com todas as telas.
     *
     * @var list<string>
     */
    protected $appends = ['avatar'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Maintenance, $this> */
    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * O usuário tem pelo menos este nível no módulo?
     *
     * Administrador passa direto: é justamente quem edita as permissões dos
     * outros, e depender do próprio mapa para isso seria um jeito fácil de se
     * trancar para fora.
     */
    public function allows(string $module, string $level): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return Permissions::satisfies($this->permissions[$module] ?? Permissions::NONE, $level);
    }

    /**
     * Mapa completo para o front decidir o que mostrar — sempre com todos os
     * módulos, para a tela nunca precisar tratar chave ausente.
     *
     * @return array<string, string>
     */
    public function permissionMap(): array
    {
        if ($this->isAdmin()) {
            return Permissions::all(Permissions::WRITE);
        }

        return Permissions::sanitize($this->permissions ?? []);
    }

    /**
     * URL da foto, ou null para o front cair nas iniciais.
     *
     * @return Attribute<?string, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null
        );
    }
}
