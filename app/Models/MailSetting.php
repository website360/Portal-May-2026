<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * O servidor de e-mail da agência.
 *
 * Um só, como a conexão do WhatsApp: a agência manda de um endereço, e tratar
 * isso como registro único evita a pergunta "por qual conta sai este e-mail?"
 * em cada módulo que vier a mandar.
 */
class MailSetting extends Model
{
    protected $fillable = [
        'host', 'port', 'username', 'password', 'encryption',
        'from_address', 'from_name', 'active',
    ];

    protected function casts(): array
    {
        return [
            // Cifrada em repouso: com ela se manda e-mail em nome da agência.
            'password' => 'encrypted',
            'port' => 'integer',
            'active' => 'boolean',
            'tested_at' => 'datetime',
        ];
    }

    /** A configuração salva, ou null enquanto ninguém preencheu nada. */
    public static function current(): ?self
    {
        return self::query()->first();
    }

    public function isUsable(): bool
    {
        return $this->active && filled($this->host) && filled($this->from_address);
    }
}
