<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Support\ContractExpiryAlert;
use App\Support\ContractPriceReviewAlert;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Avisa a agência dos contratos que se aproximam de dois marcos: o fim da
 * vigência e o próximo reajuste de preço.
 *
 * Roda todo dia. Dispara nos marcos de 30, 15, 7 e 1 dia antes de cada data —
 * como a distância cai um dia por dia, cada marco acontece uma vez, sem repetir
 * o aviso todo dia. Contrato cancelado (ou, para vencimento, ainda em rascunho)
 * não entra.
 */
class AlertExpiringContracts extends Command
{
    /** Marcos de antecedência, em dias. */
    private const MILESTONES = [1, 7, 15, 30];

    protected $signature = 'contratos:avisar-vencimento
        {--dry-run : Mostra o que seria avisado, sem enviar}';

    protected $description = 'Avisa os administradores dos contratos a vencer ou a reajustar em 30, 15, 7 ou 1 dia';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $dates = collect(self::MILESTONES)->map(fn (int $days) => Carbon::today()->addDays($days)->toDateString());

        $vencimentos = $this->notify(
            'Vencimento',
            Contract::query()
                ->whereIn('ends_at', $dates)
                ->whereNull('cancelled_at')
                // Só o que de fato vigora: assinado, ou cadastrado direto (vale pela data).
                ->where(fn ($query) => $query->whereNotNull('signed_at')->orWhere('active_without_signature', true))
                ->with('client')
                ->orderBy('ends_at')
                ->get(),
            fn (Contract $c) => (int) $c->daysLeft(),
            fn (Contract $c) => (new ContractExpiryAlert($c))->send(),
            $dryRun,
        );

        $reajustes = $this->notify(
            'Reajuste',
            Contract::query()
                ->whereIn('price_review_at', $dates)
                ->whereNull('cancelled_at')
                ->with('client')
                ->orderBy('price_review_at')
                ->get(),
            fn (Contract $c) => (int) $c->daysToReview(),
            fn (Contract $c) => (new ContractPriceReviewAlert($c))->send(),
            $dryRun,
        );

        if (! $vencimentos && ! $reajustes) {
            $this->info('Nenhum contrato em marco de vencimento ou reajuste hoje.');
        }

        return self::SUCCESS;
    }

    /**
     * Avisa um lote e imprime o que houve. Devolve se havia algo a avisar.
     *
     * @param  Collection<int, Contract>  $contracts
     * @param  callable(Contract): int  $days
     * @param  callable(Contract): array{ok: bool, message: string, sent: list<string>, errors: list<string>}  $send
     */
    private function notify(string $kind, Collection $contracts, callable $days, callable $send, bool $dryRun): bool
    {
        if ($contracts->isEmpty()) {
            return false;
        }

        $rows = [];

        foreach ($contracts as $contract) {
            $dias = max(0, $days($contract));

            $rows[] = $dryRun
                ? [$contract->number, $contract->client->name, $dias, '—']
                : [$contract->number, $contract->client->name, $dias, $send($contract)['message']];
        }

        $this->newLine();
        $this->line("<info>{$kind}</info>".($dryRun ? ' (SIMULAÇÃO)' : ''));
        $this->table(['Contrato', 'Cliente', 'Dias', 'Resultado'], $rows);

        return true;
    }
}
