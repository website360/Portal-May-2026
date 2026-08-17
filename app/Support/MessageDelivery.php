<?php

namespace App\Support;

/**
 * Por onde a mensagem sai e para quem.
 *
 * Só entra aqui o que o sistema sabe mandar de verdade. SMS e Telegram
 * apareceriam bonitos na tela e não mandariam nada — uma caixa de seleção que
 * não faz nada é pior do que a ausência dela.
 */
final class MessageDelivery
{
    public const WHATSAPP = 'whatsapp';

    public const EMAIL = 'email';

    /** @var array<string, string> */
    public const CHANNELS = [
        self::WHATSAPP => 'WhatsApp',
        self::EMAIL => 'E-mail',
    ];

    public const CLIENT = 'client';

    public const ADMINS = 'admins';

    public const ASSIGNED = 'assigned';

    /** @var array<string, string> */
    public const RECIPIENTS = [
        self::CLIENT => 'O cliente',
        self::ADMINS => 'Os administradores',
        self::ASSIGNED => 'Quem executou',
    ];

    /** @return list<string> */
    public static function channelKeys(): array
    {
        return array_keys(self::CHANNELS);
    }

    /** @return list<string> */
    public static function recipientKeys(): array
    {
        return array_keys(self::RECIPIENTS);
    }
}
