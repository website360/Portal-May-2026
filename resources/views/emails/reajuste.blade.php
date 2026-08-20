@php
    /**
     * E-mail de aviso de reajuste. HTML de e-mail é conservador de propósito:
     * tabelas e estilo inline, porque cliente de e-mail ignora <style> e flexbox.
     *
     * O texto do corpo ($message) vem do modelo editável em Configurações ›
     * Mensagens; este layout só o embrulha e destaca os números.
     */
    $paragraphs = preg_split('/\n{2,}/', trim($message));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    <!-- Cabeçalho -->
                    <tr>
                        <td style="background-color:#18181b; padding:24px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:18px; font-weight:700; color:#ffffff;">{{ $agency }}</td>
                                    <td align="right" style="font-size:13px; color:#a1a1aa;">Contrato {{ $number }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Faixa amarela -->
                    <tr><td style="height:4px; background-color:#FCC100; line-height:4px; font-size:0;">&nbsp;</td></tr>

                    <!-- Corpo -->
                    <tr>
                        <td style="padding:32px;">
                            @foreach ($paragraphs as $p)
                                <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#3f3f46;">{!! nl2br(e($p)) !!}</p>
                            @endforeach

                            <!-- Destaque dos valores -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0; border:1px solid #e4e4e7; border-radius:10px;">
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid #e4e4e7;">
                                        <span style="font-size:13px; color:#71717a;">Serviço</span><br>
                                        <span style="font-size:15px; font-weight:600; color:#18181b;">{{ $service }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="font-size:13px; color:#71717a; padding-bottom:4px;">Valor atual</td>
                                                <td align="center" style="font-size:13px; color:#71717a; padding-bottom:4px;"></td>
                                                <td align="right" style="font-size:13px; color:#71717a; padding-bottom:4px;">Novo valor</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:18px; color:#a1a1aa; text-decoration:line-through;">{{ $valueOld }}</td>
                                                <td align="center" style="font-size:18px; color:#a1a1aa;">&rarr;</td>
                                                <td align="right" style="font-size:22px; font-weight:700; color:#18181b;">{{ $valueNew }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 20px 16px;">
                                        <span style="display:inline-block; background-color:#FCC100; color:#18181b; font-size:13px; font-weight:600; padding:4px 10px; border-radius:6px;">Aumento de {{ $increase }}</span>
                                        <span style="font-size:13px; color:#71717a; margin-left:8px;">a partir de {{ $reviewDate }}</span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; font-size:13px; line-height:1.6; color:#71717a;">Qualquer dúvida, é só responder este e-mail.</p>
                        </td>
                    </tr>

                    <!-- Rodapé -->
                    <tr>
                        <td style="padding:20px 32px; background-color:#fafafa; border-top:1px solid #e4e4e7; font-size:12px; color:#a1a1aa;">
                            {{ $agency }} — este é um aviso sobre o seu contrato {{ $number }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
