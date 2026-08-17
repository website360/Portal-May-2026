<?php

namespace App\Models;

use App\Support\MessageTriggers;
use Illuminate\Database\Eloquent\Model;

/**
 * Um modelo de mensagem de WhatsApp.
 *
 * O gatilho diz onde ele pode sair, as regras dizem quando, e as variações
 * dizem de quantos jeitos — o cliente que recebe todo mês percebe o texto
 * decorado, e um texto decorado deixa de ser lido.
 */
class MessageTemplate extends Model
{
    protected $fillable = ['trigger', 'name', 'variations', 'conditions', 'priority', 'active'];

    protected function casts(): array
    {
        return [
            'variations' => 'array',
            'conditions' => 'array',
            'priority' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Uma das variações, sorteada.
     *
     * Sortear em vez de rodar em ordem porque não há onde guardar "qual foi a
     * última" que sobreviva a dois envios ao mesmo tempo — e, para o cliente,
     * o que importa é não receber sempre a mesma, não a sequência.
     */
    public function variation(): string
    {
        $variations = array_values(array_filter($this->variations ?? [], fn ($v) => filled($v)));

        return $variations === [] ? '' : $variations[array_rand($variations)];
    }

    public function triggerLabel(): string
    {
        return MessageTriggers::labelFor($this->trigger);
    }
}
