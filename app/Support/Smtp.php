<?php

namespace App\Support;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * O envio de e-mail com o servidor que a agência cadastrou.
 *
 * O .env continua valendo como padrão — em desenvolvimento o correio vai para
 * o log e ninguém precisa de servidor nenhum. Quando existe configuração ativa
 * no banco, ela sobrescreve, porque quem troca a senha do e-mail é quem usa o
 * sistema e não quem faz deploy.
 */
final class Smtp
{
    /**
     * A marca de que já empurramos uma configuração para dentro do Laravel.
     *
     * É o que deixa testar uma configuração ainda desativada: o `test()` aplica
     * o que está salvo e manda, sem que o "desativado" no banco barre o envio
     * que a pessoa acabou de pedir. Fica na configuração, e não numa propriedade
     * estática, para morrer junto com a requisição.
     */
    private const APPLIED = 'mail.vem_do_banco';

    /**
     * Empurra a configuração salva para dentro do Laravel.
     *
     * Chamada no boot da aplicação: assim vale igual para o envio da tela, o
     * do agendamento e o do comando, sem cada um lembrar de configurar.
     */
    public static function apply(?MailSetting $settings = null): bool
    {
        $settings ??= self::settings();

        if ($settings === null || ! $settings->isUsable()) {
            return false;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $settings->host,
            'port' => $settings->port,
            'username' => $settings->username,
            'password' => $settings->password,
            'encryption' => $settings->encryption ?: null,
            'timeout' => 15,
        ]);
        Config::set('mail.from', [
            'address' => $settings->from_address,
            'name' => $settings->from_name,
        ]);

        Config::set(self::APPLIED, true);

        return true;
    }

    /**
     * Manda um texto simples.
     *
     * Nunca lança, pela mesma razão do relatório de manutenção: um servidor de
     * e-mail fora do ar não pode desfazer o trabalho que já foi registrado. O
     * motivo volta na resposta, para a tela dizer o que faltou.
     *
     * @return array{ok: bool, message: string}
     */
    public static function send(string $to, string $subject, string $body): array
    {
        if (! self::configured()) {
            return ['ok' => false, 'message' => 'O e-mail não está configurado. Preencha em Configurações › E-mail.'];
        }

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => "Endereço de e-mail inválido: {$to}."];
        }

        try {
            /*
             * Texto puro, e não HTML. O que se escreve no modelo é a mesma
             * mensagem que sai no WhatsApp — convertê-la em HTML faria o
             * *negrito* virar asterisco à vista e a lista perder as quebras.
             */
            Mail::raw($body, fn ($message) => $message->to($to)->subject($subject));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'O servidor de e-mail recusou: '.$e->getMessage()];
        }

        return ['ok' => true, 'message' => 'E-mail enviado.'];
    }

    /**
     * Manda um e-mail em HTML — irmão do send() de texto puro.
     *
     * Existe para os avisos que ganham um layout: o de reajuste, por exemplo,
     * onde o valor antigo e o novo pedem uma tabela, não uma linha corrida.
     * Mesmo contrato do send(): nunca lança, o motivo volta na resposta.
     *
     * @return array{ok: bool, message: string}
     */
    public static function sendHtml(string $to, string $subject, string $html): array
    {
        if (! self::configured()) {
            return ['ok' => false, 'message' => 'O e-mail não está configurado. Preencha em Configurações › E-mail.'];
        }

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => "Endereço de e-mail inválido: {$to}."];
        }

        try {
            Mail::html($html, fn ($message) => $message->to($to)->subject($subject));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'O servidor de e-mail recusou: '.$e->getMessage()];
        }

        return ['ok' => true, 'message' => 'E-mail enviado.'];
    }

    /**
     * Dá para mandar e-mail agora?
     *
     * Em desenvolvimento o .env aponta para o log, e mandar funciona sem
     * cadastro nenhum — o que é o comportamento certo para não travar teste.
     */
    public static function configured(): bool
    {
        return Config::get(self::APPLIED, false) || (self::settings()?->isUsable() ?? Config::get('mail.default') !== 'smtp');
    }

    private static function settings(): ?MailSetting
    {
        try {
            return MailSetting::current();
        } catch (Throwable) {
            // Banco ainda sem a tabela (durante a migração inicial, por exemplo).
            return null;
        }
    }
}
