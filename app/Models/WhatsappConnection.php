<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A conexão da agência com o servidor Evolution.
 *
 * Uma só: a agência tem um número de WhatsApp, e tratar isso como registro
 * único evita a pergunta "qual instância manda esta mensagem?" em cada módulo
 * que vier a usar.
 */
class WhatsappConnection extends Model
{
    public const STATUS_CONNECTED = 'connected';

    public const STATUS_CONNECTING = 'connecting';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'base_url', 'instance', 'api_key', 'status', 'number', 'checked_at',
        /*
         * Vêm do servidor, nunca de formulário — mas precisam ser atribuíveis
         * para o cliente guardá-los ao criar ou adotar a instância. Sem isto,
         * a atribuição era descartada em silêncio e o token nunca era salvo.
         */
        'instance_token', 'instance_id',
    ];

    protected function casts(): array
    {
        return [
            // Cifrada em repouso: a chave abre o servidor inteiro.
            'api_key' => 'encrypted',
            // O token da instancia abre o envio de mensagem: vale tanto quanto a chave.
            'instance_token' => 'encrypted',
            'checked_at' => 'datetime',
        ];
    }

    /** A conexão configurada, ou null enquanto ninguém preencheu nada. */
    public static function current(): ?self
    {
        return self::query()->first();
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    /** Sem barra no fim, para montar URL sem duplicar separador. */
    public function url(string $path): string
    {
        return rtrim($this->base_url, '/').'/'.ltrim($path, '/');
    }
}
