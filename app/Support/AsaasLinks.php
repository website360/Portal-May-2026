<?php

namespace App\Support;

/**
 * O vínculo entre um lançamento do sistema e a cobrança dele no Asaas.
 *
 * Guardado num arquivo (storage/app/asaas_links.json), sem tabela. Serve para a
 * mensagem de cobrança puxar o número da fatura e o link de pagamento do Asaas.
 *
 * Formato: { "<transaction_id>": {payment_id, invoice_number, invoice_url} }
 */
final class AsaasLinks
{
    private static function file(): string
    {
        return storage_path('app/asaas_links.json');
    }

    /**
     * @return array<string, array{payment_id: ?string, invoice_number: ?string, invoice_url: ?string}>
     */
    public static function all(): array
    {
        if (! is_file(self::file())) {
            return [];
        }

        $d = json_decode((string) file_get_contents(self::file()), true);

        return is_array($d) ? $d : [];
    }

    /**
     * @return array{payment_id: ?string, invoice_number: ?string, invoice_url: ?string}|null
     */
    public static function forTransaction(int $txId): ?array
    {
        return self::all()[(string) $txId] ?? null;
    }

    public static function set(int $txId, ?string $paymentId, ?string $invoiceNumber, ?string $invoiceUrl): void
    {
        $all = self::all();
        $all[(string) $txId] = [
            'payment_id' => $paymentId,
            'invoice_number' => $invoiceNumber,
            'invoice_url' => $invoiceUrl,
        ];

        file_put_contents(self::file(), json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
